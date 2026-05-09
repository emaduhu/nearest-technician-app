require('dotenv').config();

const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const admin = require('firebase-admin');
const mongoose = require('mongoose');
const bcrypt = require('bcrypt');
const path = require('path');

const Technician = require('./models/Technician');
const User = require('./models/User');
const ServiceRequest = require('./models/ServiceRequest');

const serviceAccountPath = './serviceAccountKey.json';
let serviceAccount = null;
try {
  serviceAccount = require(serviceAccountPath);
} catch (e) {
  console.warn('No serviceAccountKey.json found in server/. Push notifications are disabled.');
}

if (serviceAccount) {
  admin.initializeApp({ credential: admin.credential.cert(serviceAccount) });
}

const app = express();
app.use(cors());
app.use(bodyParser.json({ limit: '1mb' }));
app.use('/portal', express.static(path.join(__dirname, '..', 'portal')));

const MONGO_URI = process.env.MONGO_URI || 'mongodb://localhost:27017/technician_app';
mongoose.connect(MONGO_URI)
  .then(() => console.log('MongoDB connected'))
  .catch(err => console.error('MongoDB connection failed', err));

function toNumber(value) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function haversine(lat1, lon1, lat2, lon2) {
  lat1 = toNumber(lat1);
  lon1 = toNumber(lon1);
  lat2 = toNumber(lat2);
  lon2 = toNumber(lon2);
  if ([lat1, lon1, lat2, lon2].some(v => v === null)) return null;

  const toRad = x => (x * Math.PI) / 180;
  const R = 6371;
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);
  const a = Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function technicianDto(t, distance) {
  const coordinates = t.location && Array.isArray(t.location.coordinates)
    ? t.location.coordinates
    : [null, null];
  return {
    id: String(t._id),
    userId: t.user ? String(t.user) : '',
    name: t.name,
    phone: t.phone || '',
    email: t.email,
    image: t.image || '',
    skills: t.skills || [],
    available: t.available === true,
    rating: t.rating || 0,
    location: { latitude: coordinates[1], longitude: coordinates[0] },
    distance: distance == null ? null : Number(distance.toFixed(2)),
    lastSeenAt: t.lastSeenAt,
  };
}

function userDto(user) {
  return {
    id: String(user._id),
    role: user.role,
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    lastLocation: user.lastLocation || {},
  };
}

function requestDto(request) {
  const doc = request.toObject ? request.toObject() : request;
  return {
    id: String(doc._id),
    client: doc.client && doc.client.name ? {
      id: String(doc.client._id),
      name: doc.client.name,
      phone: doc.client.phone || '',
      email: doc.client.email || '',
    } : String(doc.client || ''),
    technician: doc.technician && doc.technician.name ? technicianDto(doc.technician, doc.distanceKm) : String(doc.technician || ''),
    skill: doc.skill || '',
    description: doc.description || '',
    status: doc.status,
    clientLocation: {
      latitude: doc.clientLocation.coordinates[1],
      longitude: doc.clientLocation.coordinates[0],
    },
    technicianLocationAtRequest: doc.technicianLocationAtRequest || {},
    distanceKm: doc.distanceKm,
    responseMessage: doc.responseMessage || '',
    createdAt: doc.createdAt,
    respondedAt: doc.respondedAt,
    completedAt: doc.completedAt,
  };
}

async function sendPush(token, notification, data) {
  if (!admin.apps.length || !token) return false;
  try {
    await admin.messaging().send({
      token,
      notification,
      data: Object.fromEntries(Object.entries(data).map(([k, v]) => [k, String(v)])),
    });
    return true;
  } catch (err) {
    console.warn('Push failed', err.message);
    return false;
  }
}

app.get('/api/health', (req, res) => {
  res.json({ ok: true, firebasePush: admin.apps.length > 0 });
});

app.get('/api/portal/overview', async (req, res) => {
  try {
    const [
      totalClients,
      totalTechnicians,
      availableTechnicians,
      totalRequests,
      pendingRequests,
      acceptedRequests,
      completedRequests,
      recentRequests,
      technicians,
      skillRows,
    ] = await Promise.all([
      User.countDocuments({ role: 'client' }),
      Technician.countDocuments(),
      Technician.countDocuments({ available: true }),
      ServiceRequest.countDocuments(),
      ServiceRequest.countDocuments({ status: 'pending' }),
      ServiceRequest.countDocuments({ status: 'accepted' }),
      ServiceRequest.countDocuments({ status: 'completed' }),
      ServiceRequest.find()
        .sort({ createdAt: -1 })
        .limit(12)
        .populate('client')
        .populate('technician'),
      Technician.find().sort({ lastSeenAt: -1 }).limit(20).lean(),
      ServiceRequest.aggregate([
        { $match: { skill: { $ne: '' } } },
        { $group: { _id: '$skill', count: { $sum: 1 } } },
        { $sort: { count: -1 } },
        { $limit: 8 },
      ]),
    ]);

    res.json({
      ok: true,
      generatedAt: new Date(),
      stats: {
        totalClients,
        totalTechnicians,
        availableTechnicians,
        unavailableTechnicians: Math.max(totalTechnicians - availableTechnicians, 0),
        totalRequests,
        pendingRequests,
        acceptedRequests,
        completedRequests,
      },
      recentRequests: recentRequests.map(requestDto),
      technicians: technicians.map(t => technicianDto(t, null)),
      topSkills: skillRows.map(row => ({ skill: row._id, count: row.count })),
      firebasePush: admin.apps.length > 0,
    });
  } catch (err) {
    console.error('Portal overview error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/register', async (req, res) => {
  try {
    const { role, name, phone, email, password, token, lat, lon, skills } = req.body;
    const latitude = toNumber(lat);
    const longitude = toNumber(lon);
    if (!['client', 'technician'].includes(role)) return res.status(400).json({ error: 'Invalid role' });
    if (!name || !email || !password) return res.status(400).json({ error: 'Name, email and password are required' });
    if (latitude === null || longitude === null) return res.status(400).json({ error: 'Valid location is required' });

    const normalizedEmail = String(email).toLowerCase().trim();
    let user = await User.findOne({ email: normalizedEmail });
    const passwordHash = await bcrypt.hash(password, 10);
    if (!user) {
      user = await User.create({
        role,
        name,
        phone,
        email: normalizedEmail,
        passwordHash,
        deviceToken: token || '',
        lastLocation: { latitude, longitude, updatedAt: new Date() },
      });
    } else {
      user.role = role;
      user.name = name;
      user.phone = phone || user.phone;
      user.passwordHash = passwordHash;
      user.deviceToken = token || user.deviceToken;
      user.lastLocation = { latitude, longitude, updatedAt: new Date() };
      await user.save();
    }

    let technician = null;
    if (role === 'technician') {
      const parsedSkills = Array.isArray(skills)
        ? skills
        : String(skills || '').split(',').map(s => s.trim()).filter(Boolean);
      technician = await Technician.findOne({ email: normalizedEmail });
      if (!technician) {
        technician = await Technician.create({
          user: user._id,
          name,
          phone,
          email: normalizedEmail,
          passwordHash,
          deviceToken: token || '',
          skills: parsedSkills,
          location: { type: 'Point', coordinates: [longitude, latitude] },
          available: true,
          lastSeenAt: new Date(),
        });
      } else {
        technician.user = user._id;
        technician.name = name;
        technician.phone = phone || technician.phone;
        technician.passwordHash = passwordHash;
        technician.deviceToken = token || technician.deviceToken;
        technician.skills = parsedSkills;
        technician.location = { type: 'Point', coordinates: [longitude, latitude] };
        technician.lastSeenAt = new Date();
        await technician.save();
      }
    }

    res.json({
      ok: true,
      user: userDto(user),
      technician: technician ? technicianDto(technician, null) : null,
    });
  } catch (err) {
    if (err.code === 11000) return res.status(409).json({ error: 'Email is already registered' });
    console.error('Register error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/login', async (req, res) => {
  try {
    const { email, password, token } = req.body;
    const user = await User.findOne({ email: String(email || '').toLowerCase().trim() });
    if (!user || !(await bcrypt.compare(password || '', user.passwordHash))) {
      return res.status(401).json({
        error: 'Incorrect email or password',
        code: 'invalid_credentials',
      });
    }
    if (token) {
      user.deviceToken = token;
      await user.save();
      if (user.role === 'technician') await Technician.updateOne({ email: user.email }, { deviceToken: token });
    }
    const technician = user.role === 'technician' ? await Technician.findOne({ email: user.email }) : null;
    res.json({
      ok: true,
      user: userDto(user),
      technician: technician ? technicianDto(technician, null) : null,
    });
  } catch (err) {
    console.error('Login error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.get('/api/technicians/search', async (req, res) => {
  try {
    const lat = toNumber(req.query.lat);
    const lon = toNumber(req.query.lon);
    const maxDistance = toNumber(req.query.maxDistanceKm) || 50;
    const skill = String(req.query.skill || '').trim().toLowerCase();
    const onlyAvailable = String(req.query.available || 'true') !== 'false';
    const minRating = toNumber(req.query.minRating) || 0;

    if (lat === null || lon === null) return res.status(400).json({ error: 'lat and lon are required' });

    const filter = { rating: { $gte: minRating } };
    if (onlyAvailable) filter.available = true;
    if (skill) filter.skills = { $regex: skill, $options: 'i' };

    const techs = await Technician.find(filter).lean();
    const mapped = techs
      .map(t => {
        if (!t.location || !Array.isArray(t.location.coordinates) || t.location.coordinates.length < 2) return null;
        const [tLon, tLat] = t.location.coordinates;
        const distance = haversine(lat, lon, tLat, tLon);
        if (distance === null || distance > maxDistance) return null;
        return technicianDto(t, distance);
      })
      .filter(Boolean)
      .sort((a, b) => a.distance - b.distance);

    res.json(mapped.slice(0, 50));
  } catch (err) {
    console.error('Search error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.patch('/api/users/:id/location', async (req, res) => {
  try {
    const lat = toNumber(req.body.lat);
    const lon = toNumber(req.body.lon);
    if (lat === null || lon === null) return res.status(400).json({ error: 'Valid lat and lon are required' });

    const user = await User.findByIdAndUpdate(
      req.params.id,
      { lastLocation: { latitude: lat, longitude: lon, updatedAt: new Date() } },
      { new: true }
    );
    if (!user) return res.status(404).json({ error: 'User not found' });

    if (user.role === 'technician') {
      await Technician.updateOne(
        { email: user.email },
        {
          location: { type: 'Point', coordinates: [lon, lat] },
          lastSeenAt: new Date(),
        }
      );
    }

    res.json({ ok: true, user: userDto(user) });
  } catch (err) {
    console.error('User location update error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.patch('/api/technicians/:id/location', async (req, res) => {
  try {
    const lat = toNumber(req.body.lat);
    const lon = toNumber(req.body.lon);
    const available = req.body.available;
    if (lat === null || lon === null) return res.status(400).json({ error: 'Valid lat and lon are required' });

    const update = {
      location: { type: 'Point', coordinates: [lon, lat] },
      lastSeenAt: new Date(),
    };
    if (typeof available === 'boolean') update.available = available;

    const technician = await Technician.findByIdAndUpdate(req.params.id, update, { new: true });
    if (!technician) return res.status(404).json({ error: 'Technician not found' });
    await User.updateOne({ email: technician.email }, { lastLocation: { latitude: lat, longitude: lon, updatedAt: new Date() } });

    res.json({ ok: true, technician: technicianDto(technician, null) });
  } catch (err) {
    console.error('Location update error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.patch('/api/technicians/:id/availability', async (req, res) => {
  try {
    const technician = await Technician.findByIdAndUpdate(
      req.params.id,
      { available: req.body.available === true, lastSeenAt: new Date() },
      { new: true }
    );
    if (!technician) return res.status(404).json({ error: 'Technician not found' });
    res.json({ ok: true, technician: technicianDto(technician, null) });
  } catch (err) {
    console.error('Availability update error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/requests', async (req, res) => {
  try {
    const { clientId, technicianId, skill, description, lat, lon } = req.body;
    const latitude = toNumber(lat);
    const longitude = toNumber(lon);
    if (!clientId || latitude === null || longitude === null) {
      return res.status(400).json({ error: 'clientId, lat and lon are required' });
    }

    const client = await User.findById(clientId);
    if (!client || client.role !== 'client') return res.status(404).json({ error: 'Client not found' });

    let technician = technicianId ? await Technician.findById(technicianId) : null;
    if (!technician) {
      const techs = await Technician.find({ available: true }).lean();
      let nearest = null;
      let minDist = Infinity;
      for (const t of techs) {
        if (skill && !String((t.skills || []).join(',')).toLowerCase().includes(String(skill).toLowerCase())) continue;
        const [tLon, tLat] = t.location.coordinates;
        const distance = haversine(latitude, longitude, tLat, tLon);
        if (distance !== null && distance < minDist) {
          minDist = distance;
          nearest = t;
        }
      }
      technician = nearest ? await Technician.findById(nearest._id) : null;
    }
    if (!technician) return res.status(404).json({ error: 'No technician found' });

    const [techLon, techLat] = technician.location.coordinates;
    const distanceKm = haversine(latitude, longitude, techLat, techLon);
    const request = await ServiceRequest.create({
      client: client._id,
      technician: technician._id,
      skill: skill || '',
      description: description || '',
      clientLocation: { type: 'Point', coordinates: [longitude, latitude] },
      technicianLocationAtRequest: { latitude: techLat, longitude: techLon },
      distanceKm: distanceKm == null ? null : Number(distanceKm.toFixed(2)),
    });

    const pushed = await sendPush(
      technician.deviceToken,
      { title: 'New Client Request', body: `${client.name} requested ${skill || 'service'}` },
      { type: 'tech_request', requestId: request._id, clientName: client.name, skill: skill || '', lat: latitude, lon: longitude }
    );

    const populated = await ServiceRequest.findById(request._id).populate('client').populate('technician');
    res.status(201).json({ ok: true, request: requestDto(populated), pushed });
  } catch (err) {
    console.error('Request create error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.patch('/api/requests/:id/respond', async (req, res) => {
  try {
    const { technicianId, status, message } = req.body;
    if (!['accepted', 'rejected', 'completed', 'cancelled'].includes(status)) {
      return res.status(400).json({ error: 'Invalid status' });
    }

    const request = await ServiceRequest.findById(req.params.id).populate('client').populate('technician');
    if (!request) return res.status(404).json({ error: 'Request not found' });
    if (technicianId && String(request.technician._id) !== String(technicianId)) {
      return res.status(403).json({ error: 'Request belongs to a different technician' });
    }

    request.status = status;
    request.responseMessage = message || '';
    request.respondedAt = new Date();
    if (status === 'completed') request.completedAt = new Date();
    await request.save();

    const clientUser = request.client;
    await sendPush(
      clientUser.deviceToken,
      { title: 'Technician Response', body: `${request.technician.name} ${status} your request` },
      { type: 'request_response', requestId: request._id, status, technicianName: request.technician.name }
    );

    const populated = await ServiceRequest.findById(request._id).populate('client').populate('technician');
    res.json({ ok: true, request: requestDto(populated) });
  } catch (err) {
    console.error('Request response error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.get('/api/requests/history', async (req, res) => {
  try {
    const { clientId, technicianId, status } = req.query;
    const filter = {};
    if (clientId) filter.client = clientId;
    if (technicianId) filter.technician = technicianId;
    if (status) filter.status = status;
    if (!clientId && !technicianId) return res.status(400).json({ error: 'clientId or technicianId is required' });

    const requests = await ServiceRequest.find(filter)
      .sort({ createdAt: -1 })
      .limit(100)
      .populate('client')
      .populate('technician');
    res.json(requests.map(requestDto));
  } catch (err) {
    console.error('History error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// Backward-compatible endpoints used by older screens.
app.post('/api/request', (req, res) => {
  req.url = '/api/requests';
  app._router.handle(req, res);
});

app.post('/api/technician/login', (req, res) => {
  req.url = '/api/login';
  app._router.handle(req, res);
});

app.get('/api/technician/nearest', async (req, res) => {
  req.query.lon = req.query.lng;
  req.query.maxDistanceKm = req.query.maxDistanceKm || 10000;
  req.query.available = 'true';
  const fakeRes = {
    status: code => ({ json: body => res.status(code).json(body), send: body => res.status(code).send(body) }),
    json: body => res.json(Array.isArray(body) && body.length ? body[0] : null),
  };
  app._router.handle({ ...req, url: '/api/technicians/search', method: 'GET' }, fakeRes);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log('Server listening on', PORT));
