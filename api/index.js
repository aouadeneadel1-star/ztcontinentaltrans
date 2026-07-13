import express from "express";
import path from "path";
import { fileURLToPath } from "url";
import fs from "fs";
import dotenv from "dotenv";
import nodemailer from "nodemailer";
import { PDFDocument, rgb, StandardFonts } from "pdf-lib";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.join(__dirname, "..", ".env") });

const app = express();
app.use(express.json());

// --- API routes ---

app.post("/api/quote/calculate", (req, res) => {
  try {
    const { serviceType, departureCity, arrivalCity, weight, volume, distanceStr, urgency, refrigerated, insurance } = req.body;

    if (!departureCity || !arrivalCity) {
      return res.status(400).json({ error: "Les villes de départ et d'arrivée sont requises." });
    }

    const distance = parseFloat(distanceStr) || Math.floor(Math.random() * 800 + 150);
    let ratePerKm = 1.35, basePrice = 45, serviceLabel = "Transport Classique";

    switch (serviceType) {
      case "road": ratePerKm = 1.55; basePrice = 75; serviceLabel = "Transport Routier Standard"; break;
      case "express": ratePerKm = 2.45; basePrice = 110; serviceLabel = "Livraison Express Ultra Rapide"; break;
      case "logistics": ratePerKm = 0.85; basePrice = 150; serviceLabel = "Stockage + Transit Logistique"; break;
      case "relocation": ratePerKm = 1.95; basePrice = 250; serviceLabel = "Service Déménagement Classé"; break;
      case "distribution": ratePerKm = 1.65; basePrice = 60; serviceLabel = "Distribution Urbaine Pro"; break;
      default: ratePerKm = 1.40; basePrice = 50;
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
    if (etaHours > 24) etaString = `${Math.ceil(etaHours / 24)} à ${Math.ceil(etaHours / 24) + 1} Jours`;
    else etaString = `${etaHours} à ${etaHours + 3} Heures`;

    const co2Footprint = Math.round(distance * (cargoWeight || 1) * 0.115);
    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let code = "ZT-";
    for (let i = 0; i < 6; i++) code += chars.charAt(Math.floor(Math.random() * chars.length));

    res.json({
      trackingCode: code, serviceLabel, departureCity, arrivalCity,
      distance: Math.round(distance), etaString, co2Footprint,
      breakdown: {
        basePrice: Math.round(basePrice), transportCost: Math.round(transportCost),
        surchargeTemp: Math.round(surchargeTemp), surchargeUrgency: Math.round(surchargeUrgency),
        surchargeInsurance: Math.round(surchargeInsurance), fuelSurcharge: Math.round(fuelSurcharge),
        tollEstimation: Math.round(tollEstimation), tva: Math.round(totalTVA),
        totalTTC: Math.round(totalTTC),
      },
    });
  } catch {
    res.status(500).json({ error: "Erreur lors du calcul du devis." });
  }
});

app.post("/api/quote/send", async (req, res) => {
  try {
    const { email, name, phone, serviceType, departureCity, arrivalCity, weight } = req.body;
    if (!email || !departureCity || !arrivalCity) {
      return res.status(400).json({ error: "Email et villes requis." });
    }

    // --- Calculate quote ---
    const distance = Math.floor(Math.random() * 800 + 150);
    let ratePerKm = 1.35, basePrice = 45, serviceLabel = "Transport Classique";
    switch (serviceType) {
      case "road": ratePerKm = 1.55; basePrice = 75; serviceLabel = "Transport Routier Standard"; break;
      case "express": ratePerKm = 2.45; basePrice = 110; serviceLabel = "Livraison Express Ultra Rapide"; break;
      case "logistics": ratePerKm = 0.85; basePrice = 150; serviceLabel = "Stockage + Transit Logistique"; break;
      case "relocation": ratePerKm = 1.95; basePrice = 250; serviceLabel = "Service Déménagement Classé"; break;
      case "distribution": ratePerKm = 1.65; basePrice = 60; serviceLabel = "Distribution Urbaine Pro"; break;
    }

    const cargoWeight = parseFloat(weight) || 0.5;
    let w = 1;
    if (cargoWeight > 10) w = 1.8; else if (cargoWeight > 5) w = 1.4; else if (cargoWeight > 1) w = 1.2;

    const tc = basePrice + distance * ratePerKm * w;
    const st = tc * 0.145;
    const pe = distance > 200 ? Math.floor(distance * 0.12) : 15;
    const tva = (tc + st + pe) * 0.2;
    const total = tc + st + pe + tva;

    const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    let code = "ZT-";
    for (let i = 0; i < 6; i++) code += chars[Math.floor(Math.random() * chars.length)];

    const fmt = (n) => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");

    // --- Load logo ---
    let logoData = null;
    try {
      const r = await fetch("https://app.ztcontinentaltrans.com/logo.jpeg").catch(() => null);
      if (r?.ok) logoData = Buffer.from(await r.arrayBuffer());
    } catch {}

    // --- Generate PDF ---
    let pdfBuffer = null;
    try {
      const pdfDoc = await PDFDocument.create();
      const h = await pdfDoc.embedFont(StandardFonts.Helvetica);
      const hb = await pdfDoc.embedFont(StandardFonts.HelveticaBold);
      const page = pdfDoc.addPage([595, 842]);
      const pw = page.getWidth();
      const ml = 45, mr = 45, cw = pw - ml - mr;
      const primary = rgb(0.06, 0.09, 0.16);
      const muted = rgb(0.39, 0.41, 0.45);
      const light = rgb(0.89, 0.91, 0.94);
      const bgHeader = rgb(0.06, 0.09, 0.16);
      const bgRow = rgb(0.96, 0.97, 0.98);
      const bgTotal = rgb(0.94, 0.95, 0.97);
      let y = 800;

      // --- Dark header bar ---
      page.drawRectangle({ x: 0, y: y - 10, width: pw, height: 56, color: bgHeader });

      if (logoData) {
        try {
          const img = await pdfDoc.embedJpg(logoData);
          const s = 34 / img.width;
          page.drawImage(img, { x: ml + 4, y: y - 4, width: img.width * s, height: img.height * s });
        } catch {}
      }

      page.drawText("ZT Continental Trans", { x: 98, y: y + 10, size: 14, font: hb, color: rgb(1, 1, 1) });
      page.drawText("Transport et logistique", { x: 98, y: y - 4, size: 8, font: h, color: rgb(0.58, 0.6, 0.62) });
      page.drawText("DEVIS", { x: pw - mr, y: y + 8, size: 24, font: hb, color: rgb(1, 1, 1), textAlign: "right" });
      page.drawText(`N\u00B0 ${code}`, { x: pw - mr, y: y - 8, size: 8, font: h, color: rgb(0.58, 0.6, 0.62), textAlign: "right" });

      y -= 54;

      // --- Infos row ---
      y -= 20;
      page.drawText("DATE", { x: ml, y, size: 7, font: h, color: muted });
      page.drawText("VALIDIT\u00C9", { x: 190, y, size: 7, font: h, color: muted });
      page.drawText("SERVICE", { x: 330, y, size: 7, font: h, color: muted });

      const today = new Date().toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" });
      y -= 14;
      page.drawText(today, { x: ml, y, size: 10, font: hb, color: primary });
      page.drawText("30 jours", { x: 190, y, size: 10, font: hb, color: primary });
      page.drawText(serviceLabel, { x: 330, y, size: 10, font: hb, color: primary });

      // --- Route card ---
      y -= 28;
      page.drawRectangle({ x: ml, y: y - 12, width: cw, height: 50, color: bgRow });

      const routeX = ml + 14;
      y -= 4;
      page.drawText("TRAJET", { x: routeX, y: y - 2, size: 7, font: h, color: muted });
      y -= 18;
      page.drawText(`${departureCity}  \u2192  ${arrivalCity}`, { x: routeX, y, size: 15, font: hb, color: primary });
      y -= 16;
      page.drawText(`${distance} km`, { x: routeX, y, size: 9, font: h, color: muted });

      y -= 36;

      // --- Table header ---
      page.drawText("D\u00C9TAIL DES PRIX", { x: ml, y, size: 9, font: hb, color: primary });
      y -= 12;
      page.drawRectangle({ x: ml, y: y - 4, width: cw, height: 16, color: bgHeader });
      page.drawText("LIBELL\u00C9", { x: ml + 10, y: y + 4, size: 8, font: hb, color: rgb(1, 1, 1) });
      page.drawText("MONTANT", { x: pw - mr - 10, y: y + 4, size: 8, font: hb, color: rgb(1, 1, 1), textAlign: "right" });
      y -= 16;

      // --- Table rows ---
      const rows = [
        ["Transport de base", `${fmt(Math.round(tc))} \u20AC`],
        ["Carburant (14.5%)", `${fmt(Math.round(st))} \u20AC`],
        ["P\u00E9age", `${fmt(Math.round(pe))} \u20AC`],
        ["TVA (20%)", `${fmt(Math.round(tva))} \u20AC`],
      ];

      rows.forEach(([label, val], i) => {
        if (i % 2 === 0) page.drawRectangle({ x: ml, y: y - 2, width: cw, height: 16, color: bgRow });
        page.drawText(label, { x: ml + 10, y: y + 3, size: 9, font: h, color: primary });
        page.drawText(val, { x: pw - mr - 10, y: y + 3, size: 9, font: h, color: primary, textAlign: "right" });
        y -= 16;
      });

      // --- Total ---
      y -= 2;
      page.drawRectangle({ x: ml, y: y - 4, width: cw, height: 22, color: bgTotal });
      page.drawText("TOTAL TTC", { x: ml + 10, y: y + 5, size: 12, font: hb, color: primary });
      page.drawText(`${fmt(Math.round(total))} \u20AC`, { x: pw - mr - 10, y: y + 5, size: 14, font: hb, color: primary, textAlign: "right" });

      // --- Footer ---
      const fy = 65;
      page.drawLine({ start: { x: ml, y: fy + 6 }, end: { x: pw - mr, y: fy + 6 }, thickness: 0.5, color: light });
      page.drawText("ZT Continental Trans - 1 Place de la Boulaie, 94470 Boissy-Saint-L\u00E9ger", { x: ml, y: fy - 4, size: 7, font: h, color: muted });
      page.drawText("+33 6 13 54 35 09 | contact@ztcontinentaltrans.com", { x: ml, y: fy - 13, size: 7, font: h, color: muted });
      page.drawText("SIRET 881 077 796 00020 | TVA FR84881077796 | RCS 881 077 796 R.C.S. Creteil", { x: ml, y: fy - 22, size: 7, font: h, color: muted });

      pdfBuffer = Buffer.from(await pdfDoc.save());
    } catch (pdfErr) {
      console.error("[Quote PDF] Error:", pdfErr?.message || pdfErr);
    }

    // --- Send email with PDF ---
    if (!process.env.SMTP_USER || !process.env.SMTP_PASS) {
      return res.status(500).json({ error: "Configuration SMTP manquante." });
    }

    const transporter = nodemailer.createTransport({
      host: process.env.SMTP_HOST || "mail.ztcontinentaltrans.com",
      port: Number(process.env.SMTP_PORT) || 465,
      secure: true,
      auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
    });

    const logoBase64 = logoData ? logoData.toString("base64") : null;

    await transporter.sendMail({
      from: `"ZT Continental Trans" <${process.env.SMTP_USER}>`,
      to: email,
      subject: `Votre devis ZT Continental Trans n\u00B0${code}`,
      html: `
        <div style="max-width:600px;margin:0 auto;font-family:system-ui,sans-serif;color:#0f172a;">
          <div style="background:#0f172a;padding:20px 32px;border-radius:12px 12px 0 0;">
            ${logoBase64 ? `<img src="data:image/jpeg;base64,${logoBase64}" width="40" height="40" style="border-radius:8px;margin-right:12px;vertical-align:middle" />` : ""}
            <span style="color:#fff;font-size:20px;font-weight:700;vertical-align:middle;">ZT Continental Trans</span>
            <p style="color:#94a3b8;font-size:13px;margin:4px 0 0;">Transport et logistique</p>
          </div>
          <div style="border:1px solid #e2e8f0;border-top:0;padding:32px;border-radius:0 0 12px 12px;">
            <p style="font-size:14px;color:#64748b;margin:0 0 4px;">DEVIS N\u00B0 ${code}</p>
            <p style="font-size:24px;font-weight:700;margin:0;">${fmt(Math.round(total))} \u20AC</p>
            <p style="font-size:13px;color:#64748b;margin:4px 0 20px;">${departureCity} \u2192 ${arrivalCity} \u00B7 ${distance} km</p>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Service</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">${serviceLabel}</td></tr>
              <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Transport</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;">${fmt(Math.round(tc))} \u20AC</td></tr>
              <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">Carburant</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;">${fmt(Math.round(st))} \u20AC</td></tr>
              <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">P\u00E9age</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;">${fmt(Math.round(pe))} \u20AC</td></tr>
              <tr><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#64748b;">TVA (20%)</td><td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;">${fmt(Math.round(tva))} \u20AC</td></tr>
              <tr><td style="padding:8px 0;color:#0f172a;font-weight:700;">TOTAL TTC</td><td style="padding:8px 0;text-align:right;font-weight:700;">${fmt(Math.round(total))} \u20AC</td></tr>
            </table>
            <p style="font-size:12px;color:#94a3b8;margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;">
              1 Place de la Boulaie, 94470 Boissy-Saint-L\u00E9ger<br>
              +33 6 13 54 35 09 \u00B7 contact@ztcontinentaltrans.com<br>
              SIRET 881 077 796 00020 \u00B7 TVA FR84881077796 \u00B7 RCS 881 077 796 R.C.S. Creteil
            </p>
          </div>
        </div>
      `,
      ...(pdfBuffer ? {
        attachments: [{
          filename: `devis-zt-${code.toLowerCase()}.pdf`,
          content: pdfBuffer,
          contentType: "application/pdf",
        }],
      } : {}),
    });

    // --- Notify owner ---
    try {
      await transporter.sendMail({
        from: `"ZT Continental Trans" <${process.env.SMTP_USER}>`,
        to: process.env.CONTACT_EMAIL || "contact@ztcontinentaltrans.com",
        subject: `Nouveau devis n\u00B0${code} - ${departureCity} \u2192 ${arrivalCity}`,
        html: `
          <div style="max-width:600px;font-family:system-ui,sans-serif;color:#0f172a;">
            <div style="background:#0f172a;padding:16px 24px;border-radius:8px 8px 0 0;">
              <span style="color:#fff;font-size:18px;font-weight:700;">Nouveau devis</span>
            </div>
            <div style="border:1px solid #e2e8f0;padding:24px;">
              <p><strong>Devis n\u00B0${code}</strong></p>
              <p>${departureCity} \u2192 ${arrivalCity} (${distance} km)</p>
              <p>Service : ${serviceLabel}</p>
              <p style="font-size:24px;font-weight:700;color:#2563eb;">${fmt(Math.round(total))} \u20AC</p>
              <hr style="border:none;border-top:1px solid #e2e8f0;margin:16px 0;">
              <p style="color:#64748b;font-size:13px;">
                Client : ${name || "non renseign\u00E9"}<br>
                Email : ${email}<br>
                T\u00E9l : ${phone || "non renseign\u00E9"}
              </p>
            </div>
          </div>
        `,
      });
    } catch (notifErr) {
      console.error("[Quote Notif] Error:", notifErr?.message || notifErr);
    }

    res.json({ success: true });
  } catch (error) {
    console.error("[Quote Send] Error:", error?.message || error);
    res.status(500).json({ error: "Erreur lors de l'envoi du devis." });
  }
});

// --- Serve static files ---
const distPath = path.join(__dirname, "..", "dist");
app.use(express.static(distPath));
app.get("*", (req, res) => {
  res.sendFile(path.join(distPath, "index.html"), (err) => {
    if (err) res.status(404).json({ error: "Page non trouvée" });
  });
});

export default app;
