require('dotenv').config();
const express = require('express');
const cors = require('cors');
const bodyParser = require('body-parser');
const admin = require('firebase-admin');
const mongoose = require('mongoose');
const Technician = require('./models/Technician');

const serviceAccountPath = './serviceAccountKey.json';
let serviceAccount = null;
try {
  serviceAccount = require(serviceAccountPath);
} catch (e) {
  console.warn(
    'No serviceAccountKey.json found in server/. Place your Firebase service account JSON at',
    serviceAccountPath
  );
}

if (serviceAccount) {
  admin.initializeApp({ credential: admin.credential.cert(serviceAccount) });
} else {
  console.warn(
    'Firebase Admin not initialized - push will fail until serviceAccountKey.json is provided.'
  );
}

const app = express();
app.use(cors());
app.use(bodyParser.json());

const MONGO_URI = process.env.MONGO_URI || 'mongodb://localhost:27017/technician_app';
mongoose.connect(MONGO_URI)
  .then(() => console.log('MongoDB connected'))
  .catch(err => console.error(err));

  function haversine(lat1, lon1, lat2, lon2) {
    const toRad = x => (x * Math.PI) / 180;
    const R = 6371;
    lat1 = parseFloat(lat1); lon1 = parseFloat(lon1); lat2 = parseFloat(lat2); lon2 = parseFloat(lon2);
    if ([lat1, lon1, lat2, lon2].some(isNaN)) return null;
    const dLat = toRad(lat2 - lat1);
    const dLon = toRad(lon2 - lon1);
    const a = Math.sin(dLat/2)**2 + Math.cos(toRad(lat1))*Math.cos(toRad(lat2))*Math.sin(dLon/2)**2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

// Register endpoint
app.post('/api/register', async (req, res) => {
  const { role, token, lat, lon, name } = req.body;
  if (!role || !token || lat == null || lon == null) return res.status(400).send('Missing');
  try {
    if (role === 'technician') {
      let t = await Technician.findOne({ email: name + '@example.com' });
      if (!t) {
        t = await Technician.create({
          name,
          email: name + '@example.com',
          passwordHash: 'seed',
          latitude: lat,
          longitude: lon,
          deviceToken: token
        });
      } else {
        t.deviceToken = token;
        t.latitude = lat;
        t.longitude = lon;
        await t.save();
      }
      return res.json({ ok: true, technician: t });
    } else {
      return res.json({ ok: true });
    }
  } catch (err) {
    console.error(err);
    res.status(500).json({ error: err.message });
  }
});

// Request a technician
// Request a technician
app.post('/api/request', async (req, res) => {
  const { clientName, lat, lon } = req.body;
  if (lat == null || lon == null) return res.status(400).send('Missing location');

  try {
    const techs = await Technician.find();
    if (!techs || techs.length === 0) return res.status(404).send('No technicians available');

    function haversine(lat1, lon1, lat2, lon2) {
      const toRad = x => x * Math.PI / 180;
      const R = 6371;
      const dLat = toRad(lat2 - lat1);
      const dLon = toRad(lon2 - lon1);
      const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2;
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    let nearest = null;
    let minDist = Infinity;

    for (const t of techs) {
      if (!t.location || !Array.isArray(t.location.coordinates) || t.location.coordinates.length < 2) continue;
      const [tLon, tLat] = t.location.coordinates;
      const d = haversine(lat, lon, tLat, tLon);
      if (d < minDist) {
        minDist = d;
        nearest = t;
      }
    }

    if (!nearest) return res.status(404).send('No technician with location found');

    // Check if the technician has a valid FCM token
    if (!nearest.deviceToken || nearest.deviceToken.trim() === '') {
      console.warn(`Technician ${nearest.name} has no FCM token; push notification skipped.`);
      return res.json({
        ok: true,
        technician: nearest.name,
        distance_km: minDist,
        pushed: false,
        message: 'Technician has no FCM token'
      });
    }

    if (!admin.apps.length) {
      console.warn('Firebase admin not initialized; cannot send push.');
      return res.json({
        ok: true,
        technician: nearest.name,
        distance_km: minDist,
        pushed: false,
        message: 'Firebase not initialized'
      });
    }

    const message = {
      token: nearest.deviceToken, // this must exist now
      data: {
        type: 'tech_request',
        requestId: 'req-' + Date.now(),
        clientName: clientName || 'Client',
        lat: String(lat),
        lon: String(lon),
      },
      notification: {
        title: 'New Request',
        body: `${clientName || 'Client'} requested help`
      },
    };

    const resp = await admin.messaging().send(message);
    console.log('Push response:', resp);

    res.json({
      ok: true,
      technician: nearest.name,
      distance_km: minDist,
      pushed: true,
      message: 'Push sent successfully'
    });

  } catch (err) {
    console.error(err);
    res.status(500).json({ error: err.message });
  }
});

// Technician login
app.post('/api/technician/login', async (req, res) => {
  const { email, password } = req.body;
  const t = await Technician.findOne({ email });
  if (!t) return res.status(404).json({ message: 'Technician not found' });
  res.json({ ok: true, id: t._id, name: t.name, latitude: t.latitude, longitude: t.longitude });
});

// Get nearest technician
app.get('/api/technician/nearest', async (req, res) => {
  const lat = parseFloat(req.query.lat);
  const lng = parseFloat(req.query.lng);
  const techs = await Technician.find();
  if (!techs || techs.length === 0) return res.status(404).send('No technicians');

function haversine(lat1, lon1, lat2, lon2) {
  // Convert inputs to numbers
  lat1 = parseFloat(lat1);
  lon1 = parseFloat(lon1);
  lat2 = parseFloat(lat2);
  lon2 = parseFloat(lon2);

  // Check for invalid values
  if (
    isNaN(lat1) || isNaN(lon1) ||
    isNaN(lat2) || isNaN(lon2)
  ) return null; // or throw Error

  const toRad = x => x * Math.PI / 180;
  const R = 6371; // Earth radius in km
  const dLat = toRad(lat2 - lat1);
  const dLon = toRad(lon2 - lon1);

  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
    Math.sin(dLon / 2) ** 2;

  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

  return R * c;
}


  let nearest = null;
  let minDist = Infinity;

  for (const t of techs) {
    if (!t.location || !Array.isArray(t.location.coordinates) || t.location.coordinates.length < 2) continue;
    const [tLon, tLat] = t.location.coordinates;
    const d = haversine(lat, lng, tLat, tLon);
    if (d < minDist) {
      minDist = d;
      nearest = t;
    }
  }

  if (!nearest) return res.status(404).send('No technician with location found');

  res.json(nearest);
});

// Real-time search endpoint
app.get('/api/technicians/search', async (req, res) => {
  try {
    const { skill, lat, lon } = req.query;
    if (!lat || !lon) return res.status(400).json({ error: 'lat and lon required' });

    // Only available technicians
    let techs = await Technician.find({ available: true }).lean();

    if (!techs || techs.length === 0) return res.json([]); // empty list

    // filter by skill if provided
    if (skill && skill.trim().length > 0) {
      const s = skill.trim().toLowerCase();
      techs = techs.filter(t => Array.isArray(t.skills) && t.skills.some(sk => sk.toLowerCase().contains ? sk.toLowerCase().includes(s) : sk.toLowerCase().indexOf(s) !== -1));
    }

    // compute distance and map
    const mapped = techs.map(t => {
      if (!t.location || !Array.isArray(t.location.coordinates) || t.location.coordinates.length < 2) return null;
      const [tLon, tLat] = t.location.coordinates;
      const distance = haversine(lat, lon, tLat, tLon);
      if (distance === null) return null;
      return {
        id: t._id,
        name: t.name,
        phone: t.phone,
        email: t.email,
        image: t.image,
        skills: t.skills,
        available: t.available,
        location: { latitude: tLat, longitude: tLon },
        distance: Number(distance.toFixed(2)),
        deviceToken: t.deviceToken || ''
      };
    }).filter(Boolean);

    // sort by distance and return top 20
    mapped.sort((a,b) => a.distance - b.distance);
    res.json(mapped.slice(0, 20));
  } catch (err) {
    console.error('Search error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// Request endpoint: accept technicianId
app.post('/api/request', async (req, res) => {
  try {
    const { clientName, lat, lon, technicianId } = req.body;
    if (!clientName || lat == null || lon == null) return res.status(400).json({ error: 'Missing' });

    let tech = null;
    if (technicianId) {
      tech = await Technician.findById(technicianId).lean();
    } else {
      // fallback: nearest available
      const techs = await Technician.find({ available: true }).lean();
      let minDist = Infinity;
      for (const t of techs) {
        if (!t.location || !Array.isArray(t.location.coordinates) || t.location.coordinates.length < 2) continue;
        const [tLon, tLat] = t.location.coordinates;
        const d = haversine(lat, lon, tLat, tLon);
        if (d !== null && d < minDist) { minDist = d; tech = t; }
      }
    }

    if (!tech) return res.status(404).json({ error: 'No technician found' });

    // If Firebase admin configured and deviceToken present, send push
    if (admin.apps.length && tech.deviceToken) {
      const message = {
        token: tech.deviceToken,
        notification: {
          title: 'New Client Request',
          body: `${clientName} requested help nearby`,
        },
        data: { clientName, lat: String(lat), lon: String(lon), type: 'tech_request' },
      };
      try {
        const resp = await admin.messaging().send(message);
        console.log('Push sent', resp);
      } catch (pushErr) {
        console.warn('Push failed', pushErr);
      }
    } else {
      console.warn('Push skipped: no admin or deviceToken');
    }

    return res.json({ ok: true, technician: { id: tech._id, name: tech.name }, pushed: !!(tech.deviceToken && admin.apps.length) });
  } catch (err) {
    console.error('Request error', err);
    res.status(500).json({ error: 'Server error' });
  }
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log('Server listening on', PORT));
