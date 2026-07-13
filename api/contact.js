import nodemailer from "nodemailer";

export default async function handler(req, res) {
  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");

  if (req.method === "OPTIONS") return res.status(200).end();
  if (req.method !== "POST") {
    return res.status(405).json({ error: "Méthode non autorisée" });
  }

  try {
    let body;
    try {
      body = typeof req.body === "string" ? JSON.parse(req.body) : req.body;
    } catch {
      return res.status(400).json({ error: "Corps de requête invalide." });
    }

    const { name, email, phone, message } = body || {};

    if (!name || !email || !message) {
      return res.status(400).json({ error: "Nom, email et message sont requis." });
    }

    if (!process.env.SMTP_USER || !process.env.SMTP_PASS) {
      console.error("[Contact] SMTP_USER or SMTP_PASS not set");
      return res.status(500).json({ error: "Configuration SMTP manquante." });
    }

    const transporter = nodemailer.createTransport({
      host: process.env.SMTP_HOST || "mail.ztcontinentaltrans.com",
      port: Number(process.env.SMTP_PORT) || 465,
      secure: true,
      auth: {
        user: process.env.SMTP_USER,
        pass: process.env.SMTP_PASS,
      },
    });

    await transporter.sendMail({
      from: `"${name}" <${process.env.SMTP_USER}>`,
      replyTo: email,
      to: process.env.CONTACT_EMAIL || "contact@ztcontinentaltrans.com",
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
  } catch (error) {
    console.error("[Contact] Error:", error);
    res.status(500).json({ error: "Erreur lors de l'envoi du message." });
  }
}
