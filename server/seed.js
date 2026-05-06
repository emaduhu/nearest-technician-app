require('dotenv').config();

const mongoose = require('mongoose');
const bcrypt = require('bcrypt');
const Technician = require('./models/Technician');
const User = require('./models/User');
const ServiceRequest = require('./models/ServiceRequest');

const MONGO_URI = process.env.MONGO_URI || 'mongodb://localhost:27017/technician_app';

const technicians = [
  {
    name: 'Maria Hassan',
    phone: '+255713555888',
    email: 'maria.hassan@example.com',
    skills: ['Refrigeration', 'Air Conditioning'],
    image: 'https://randomuser.me/api/portraits/women/2.jpg',
    coordinates: [39.2695, -6.8206],
    available: true,
  },
  {
    name: 'Peter Nyerere',
    phone: '+255717777999',
    email: 'peter.nyerere@example.com',
    skills: ['Carpentry', 'Painting'],
    image: 'https://randomuser.me/api/portraits/men/3.jpg',
    coordinates: [35.7432, -6.1630],
    available: true,
  },
  {
    name: 'Asha Mwinyi',
    phone: '+255714000333',
    email: 'asha.mwinyi@example.com',
    skills: ['IT Support', 'Networking'],
    image: 'https://randomuser.me/api/portraits/women/4.jpg',
    coordinates: [38.9956, -6.8278],
    available: false,
  },
  {
    name: 'Joseph Kimaro',
    phone: '+255719444222',
    email: 'joseph.kimaro@example.com',
    skills: ['Plumbing', 'Tiling'],
    image: 'https://randomuser.me/api/portraits/men/5.jpg',
    coordinates: [39.2102, -6.8444],
    available: true,
  },
];

(async () => {
  try {
    await mongoose.connect(MONGO_URI);
    console.log('Connected to MongoDB');

    await ServiceRequest.deleteMany({});
    await Technician.deleteMany({});
    await User.deleteMany({ email: { $in: technicians.map(t => t.email) } });

    const passwordHash = await bcrypt.hash('password123', 10);

    for (const item of technicians) {
      const user = await User.create({
        role: 'technician',
        name: item.name,
        phone: item.phone,
        email: item.email,
        passwordHash,
        lastLocation: {
          latitude: item.coordinates[1],
          longitude: item.coordinates[0],
          updatedAt: new Date(),
        },
      });

      await Technician.create({
        user: user._id,
        name: item.name,
        phone: item.phone,
        email: item.email,
        passwordHash,
        skills: item.skills,
        image: item.image,
        location: { type: 'Point', coordinates: item.coordinates },
        available: item.available,
        lastSeenAt: new Date(),
      });
    }

    console.log(`Inserted ${technicians.length} technicians. Default password: password123`);
    process.exit(0);
  } catch (err) {
    console.error('Error seeding technicians:', err);
    process.exit(1);
  }
})();
