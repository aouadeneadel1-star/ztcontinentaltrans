import express from "express";
import path from "path";
import dotenv from "dotenv";
import nodemailer from "nodemailer";

dotenv.config({ path: path.join(process.cwd(), ".env") });

const app = express();
const PORT = Number(process.env.PORT) || 3000;

app.use(express.json());

const transporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST || "mail.ztcontinentaltrans.com",
  port: Number(process.env.SMTP_PORT) || 465,
  secure: true,
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASS,
  },
});

const TO_EMAIL = process.env.CONTACT_EMAIL || "contact@ztcontinentaltrans.com";

// API Endpoint: Dynamic Calculator de Devis
app.post("/api/quote/calculate", (req, res) => {
  try {
    const {
      serviceType,
      departureCity,
      arrivalCity,
      weight,
      volume,
      distanceStr,
      urgency,
      refrigerated,
      insurance,
    } = req.body;

    if (!departureCity || !arrivalCity) {
      return res.status(400).json({ error: "Les villes de départ et d'arrivée sont requises." });
    }

    const distance = parseFloat(distanceStr) || Math.floor(Math.random() * 800 + 150);

    let ratePerKm = 1.35;
    let basePrice = 45;
    let serviceLabel = "Transport Classique";

    switch (serviceType) {
      case "road":
        ratePerKm = 1.55;
        basePrice = 75;
        serviceLabel = "Transport Routier Standard";
        break;
      case "express":
        ratePerKm = 2.45;
        basePrice = 110;
        serviceLabel = "Livraison Express Ultra Rapide";
        break;
      case "logistics":
        ratePerKm = 0.85;
        basePrice = 150;
        serviceLabel = "Stockage + Transit Logistique";
        break;
      case "relocation":
        ratePerKm = 1.95;
        basePrice = 250;
        serviceLabel = "Service Déménagement Classé";
        break;
      case "distribution":
        ratePerKm = 1.65;
        basePrice = 60;
        serviceLabel = "Distribution Urbaine Pro";
        break;
      default:
        ratePerKm = 1.4;
        basePrice = 50;
    }

    const cargoWeight = parseFloat(weight) || 0.5;
    let weightMultiplier = 1;
    if (cargoWeight > 10) weightMultiplier = 1.8;
    else if (cargoWeight > 5) weightMultiplier = 1.4;
    else if (cargoWeight > 1) weightMultiplier = 1.2;

    let transportCost = basePrice + distance * ratePerKm * weightMultiplier;
    let surchargeTemp = 0;
    if (refrigerated) surchargeTemp = transportCost * 0.22;

    let surchargeUrgency = 0;
    if (urgency === "high") surchargeUrgency = transportCost * 0.3;

    let surchargeInsurance = 0;
    if (insurance) surchargeInsurance = transportCost * 0.08 + 15;

    const subtotal = transportCost + surchargeTemp + surchargeUrgency + surchargeInsurance;
    const fuelSurcharge = subtotal * 0.145;
    const tollEstimation = distance > 200 ? Math.floor(distance * 0.12) : 15;
    const totalTVA = (subtotal + fuelSurcharge + tollEstimation) * 0.2;
    const totalTTC = subtotal + fuelSurcharge + tollEstimation + totalTVA;

    let etaHours = Math.ceil(distance / 70);
    if (urgency === "high") etaHours = Math.ceil(distance / 85);

    let etaString = "Dans la journée";
    if (etaHours > 24) {
      etaString = `${Math.ceil(etaHours / 24)} à ${Math.ceil(etaHours / 24) + 1} Jours`;
    } else {
      etaString = `${etaHours} à ${etaHours + 3} Heures`;
    }

    const co2Footprint = Math.round(distance * (cargoWeight || 1) * 0.115);

    const characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let codeResult = "ZT-";
    for (let i = 0; i < 6; i++) {
      codeResult += characters.charAt(Math.floor(Math.random() * characters.length));
    }

    res.json({
      trackingCode: codeResult,
      serviceLabel,
      departureCity,
      arrivalCity,
      distance: Math.round(distance),
      etaString,
      co2Footprint,
      breakdown: {
        basePrice: Math.round(basePrice),
        transportCost: Math.round(transportCost),
        surchargeTemp: Math.round(surchargeTemp),
        surchargeUrgency: Math.round(surchargeUrgency),
        surchargeInsurance: Math.round(surchargeInsurance),
        fuelSurcharge: Math.round(fuelSurcharge),
        tollEstimation: Math.round(tollEstimation),
        tva: Math.round(totalTVA),
        totalTTC: Math.round(totalTTC),
      },
    });
  } catch (error: any) {
    res.status(500).json({ error: "Erreur lors du calcul du devis." });
  }
});

// API Endpoint: Contact form
app.post("/api/contact", async (req, res) => {
  try {
    const { name, email, phone, message } = req.body;

    if (!name || !email || !message) {
      return res.status(400).json({ error: "Nom, email et message sont requis." });
    }

    await transporter.sendMail({
      from: `"${name}" <${process.env.SMTP_USER}>`,
      replyTo: email,
      to: TO_EMAIL,
      subject: `Nouveau message de ${name} - ZT Continental Trans`,
      html: `
        <h2>Nouveau message du formulaire de contact</h2>
        <p><strong>Nom :</strong> ${name}</p>
        <p><strong>Email :</strong> ${email}</p>
        <p><strong>Téléphone :</strong> ${phone || "Non renseigné"}</p>
        <hr />
        <p><strong>Message :</strong></p>
        <p>${message}</p>
      `,
    });

    res.json({ success: true });
  } catch (error: any) {
    console.error("[Contact] Error:", error);
    res.status(500).json({ error: "Erreur lors de l'envoi du message." });
  }
});

async function boot() {
  const serveStatic = () => {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  };

  if (process.env.NODE_ENV !== "production") {
    try {
      const { createServer: createViteServer } = await import("vite");
      const vite = await createViteServer({
        server: { middlewareMode: true },
        appType: "spa",
      });
      app.use(vite.middlewares);
    } catch (e: any) {
      console.warn("[Vite Fallback] Failed to dynamically load 'vite' package. Falling back to static files serving. Error:", e?.message || e);
      serveStatic();
    }
  } else {
    serveStatic();
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`[ZT SERVEUR RUNNING] on http://localhost:${PORT}`);
  });
}

boot().catch((err) => {
  console.error("Failed to start server:", err);
});