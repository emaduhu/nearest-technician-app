const mongoose = require('mongoose');

const TechnicianSchema = new mongoose.Schema({
  name: { type: String, required: true },
  phone: { type: String },
  email: { type: String, required: true, unique: true },
  passwordHash: { type: String, required: true },
  deviceToken: { type: String }, // FCM token
  skills: { type: [String], default: [] },
  image: { type: String, default: '' },
  location: {
    type: {
      type: String,
      enum: ['Point'],
      default: 'Point'
    },
    coordinates: {
      type: [Number], // [longitude, latitude]
      required: true
    }
  },
  available: { type: Boolean, default: true },
  rating: { type: Number, default: 4.5 },
}, { timestamps: true });

// Create 2dsphere index for geospatial queries
TechnicianSchema.index({ location: '2dsphere' });

module.exports = mongoose.model('Technician', TechnicianSchema);
