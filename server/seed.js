const mongoose = require("mongoose");
const bcrypt = require("bcrypt");

const MONGO_URI = "mongodb://localhost:27017/technician_app";

const technicianSchema = new mongoose.Schema({
  name: String,
  phone: String,
  email: String,
  passwordHash: String,    // Hashed password
  deviceToken: { type: String, default: "" }, // FCM device token
  skills: [String],
  image: { type: String, default: "" }, // URL or path to image
  location: {
    type: { type: String, default: "Point" },
    coordinates: [Number], // [longitude, latitude]
  },
  available: { type: Boolean, default: true },
  rating: { type: Number, default: 4.5 },
  createdAt: { type: Date, default: Date.now },
});

const Technician = mongoose.model("Technician", technicianSchema);

(async () => {
  try {
    await mongoose.connect(MONGO_URI);
    console.log("✅ Connected to MongoDB");

    await Technician.deleteMany({});
    console.log("🧹 Cleared existing technicians");

    // Default password for all technicians
    const defaultPassword = "password123";
    const hashedPassword = await bcrypt.hash(defaultPassword, 10);

    const technicians = [
//      {
//        name: "John Doe",
//        phone: "+255712345678",
//        email: "john.doe@example.com",
//        passwordHash: hashedPassword,
//        deviceToken: "",
//        skills: ["Electrical", "Plumbing"],
//        image: "https://randomuser.me/api/portraits/men/1.jpg",
//        location: { type: "Point", coordinates: [39.2026, -6.7924] }, // Dar es Salaam
//        available: true,
//      },
      {
        name: "Maria Hassan",
        phone: "+255713555888",
        email: "maria.hassan@example.com",
        passwordHash: hashedPassword,
        deviceToken: "",
        skills: ["Refrigeration", "Air Conditioning"],
        image: "https://randomuser.me/api/portraits/women/2.jpg",
        location: { type: "Point", coordinates: [39.2695, -6.8206] }, // Masaki
        available: true,
      },
      {
        name: "Peter Nyerere",
        phone: "+255717777999",
        email: "peter.nyerere@example.com",
        passwordHash: hashedPassword,
        deviceToken: "",
        skills: ["Carpentry", "Painting"],
        image: "https://randomuser.me/api/portraits/men/3.jpg",
        location: { type: "Point", coordinates: [35.7432, -6.1630] }, // Dodoma
        available: true,
      },
      {
        name: "Asha Mwinyi",
        phone: "+255714000333",
        email: "asha.mwinyi@example.com",
        passwordHash: hashedPassword,
        deviceToken: "",
        skills: ["IT Support", "Networking"],
        image: "https://randomuser.me/api/portraits/women/4.jpg",
        location: { type: "Point", coordinates: [38.9956, -6.8278] }, // Kibaha
        available: false,
      },
      {
        name: "Joseph Kimaro",
        phone: "+255719444222",
        email: "joseph.kimaro@example.com",
        passwordHash: hashedPassword,
        deviceToken: "",
        skills: ["Plumbing", "Tiling"],
        image: "https://randomuser.me/api/portraits/men/5.jpg",
        location: { type: "Point", coordinates: [39.2102, -6.8444] }, // Mikocheni
        available: true,
      },
    ];

    const inserted = await Technician.insertMany(technicians);
    console.log(`✅ Inserted ${inserted.length} technicians successfully`);
    process.exit(0);
  } catch (err) {
    console.error("❌ Error seeding technicians:", err);
    process.exit(1);
  }
})();
