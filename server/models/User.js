const mongoose = require('mongoose');

const UserSchema = new mongoose.Schema({
  role: { type: String, enum: ['client', 'technician'], required: true },
  name: { type: String, required: true },
  phone: { type: String, default: '' },
  email: { type: String, required: true, unique: true, lowercase: true, trim: true },
  passwordHash: { type: String, required: true },
  deviceToken: { type: String, default: '' },
  lastLocation: {
    latitude: { type: Number },
    longitude: { type: Number },
    updatedAt: { type: Date },
  },
}, { timestamps: true });

module.exports = mongoose.model('User', UserSchema);
