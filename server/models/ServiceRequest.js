const mongoose = require('mongoose');

const ServiceRequestSchema = new mongoose.Schema({
  client: { type: mongoose.Schema.Types.ObjectId, ref: 'User', required: true },
  technician: { type: mongoose.Schema.Types.ObjectId, ref: 'Technician', required: true },
  skill: { type: String, default: '' },
  description: { type: String, default: '' },
  status: {
    type: String,
    enum: ['pending', 'accepted', 'rejected', 'completed', 'cancelled'],
    default: 'pending',
  },
  clientLocation: {
    type: {
      type: String,
      enum: ['Point'],
      default: 'Point',
    },
    coordinates: {
      type: [Number],
      required: true,
    },
  },
  technicianLocationAtRequest: {
    latitude: { type: Number },
    longitude: { type: Number },
  },
  distanceKm: { type: Number },
  responseMessage: { type: String, default: '' },
  respondedAt: { type: Date },
  completedAt: { type: Date },
}, { timestamps: true });

ServiceRequestSchema.index({ client: 1, createdAt: -1 });
ServiceRequestSchema.index({ technician: 1, createdAt: -1 });
ServiceRequestSchema.index({ status: 1, createdAt: -1 });
ServiceRequestSchema.index({ clientLocation: '2dsphere' });

module.exports = mongoose.model('ServiceRequest', ServiceRequestSchema);
