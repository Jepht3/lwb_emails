#!/usr/bin/env node
// Assemble en-tête + corps + pied de chaque template WHMCS avec des données fictives,
// pour prévisualiser les e-mails dans un navigateur.
// Usage : node tools/build-previews.js  ->  previews/*.html
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');

const sample = {
  client_first_name: 'Jephté',
  client_email: 'jephte@exemple.cd',
  whmcs_url: 'https://my.lewebmax.com/',
  signature: "L'équipe LEWEBMAX",
  pw_reset_url: 'https://my.lewebmax.com/index.php?rp=/password/reset/redeem/abc123',
  invoice_num: '2026-1042',
  invoice_date_created: '09/10/2026',
  invoice_date_due: '16/10/2026',
  invoice_total: '29,88 $ USD',
  invoice_balance: '29,88 $ USD',
  invoice_payment_method: 'Mobile Money',
  invoice_link: 'https://my.lewebmax.com/viewinvoice.php?id=1042',
  invoice_last_payment_amount: '29,88 $ USD',
  invoice_last_payment_transid: 'MP261009.1432.A12345',
  service_id: '318',
  service_product_name: 'Hébergement Pro',
  service_domain: 'monentreprise.cd',
  service_server_hostname: 'srv1.lewebmax.com',
  service_username: 'monentre',
  service_password: 'x7#Pq2!vLm9',
  service_server_ip: '102.68.10.25',
  service_ns1: 'ns1.lewebmax.com',
  service_ns2: 'ns2.lewebmax.com',
  service_first_payment_amount: '29,88 $ USD',
  service_recurring_amount: '29,88 $ USD',
  service_billing_cycle: 'Annuel',
  service_next_due_date: '09/10/2027',
  service_suspension_reason: 'Facture impayée',
  domain_id: '77',
  domain_name: 'monentreprise.cd',
  domain_reg_period: '1 an',
  domain_first_payment_amount: '35,00 $ USD',
  domain_recurring_amount: '35,00 $ USD',
  domain_next_due_date: '09/10/2027',
  domain_expiry_date: '09/11/2026',
  domain_days_until_expiry: '30',
  ticket_id: '482913',
  ticket_subject: 'Configuration de mes e-mails sur Outlook',
  ticket_department: 'Support technique',
  ticket_priority: 'Moyenne',
  ticket_status: 'Répondu',
  ticket_url: 'https://my.lewebmax.com/viewticket.php?tid=482913',
  ticket_message:
    'Bonjour Jephté,<br><br>Pour configurer votre adresse sur Outlook, utilisez le serveur <strong>mail.monentreprise.cd</strong>, port IMAP 993 (SSL) et SMTP 465 (SSL).<br><br>N\'hésitez pas si vous avez besoin d\'aide.',
};

const fill = (html) =>
  html.replace(/\{\$([a-z0-9_]+)\}/g, (m, k) => (k in sample ? sample[k] : m));

const css = read('whmcs/email-css.css');
const header = read('whmcs/header.html');
const footer = read('whmcs/footer.html');
const list = JSON.parse(read('whmcs/templates.json'));

const page = (title, inner) => `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${title}</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>${css}</style>
</head>
<body style="margin:0;padding:0;background-color:#f4f5fb;">
${inner}
</body>
</html>
`;

const out = path.join(root, 'previews');
fs.mkdirSync(out, { recursive: true });

const links = [];
for (const t of list) {
  const body = read(`whmcs/templates/${t.fichier}`);
  const subject = fill(t.sujet);
  fs.writeFileSync(path.join(out, t.fichier), page(subject, fill(header + body + footer)));
  links.push(`<li><a href="${t.fichier}">${subject}</a> <small>(${t.template_whmcs})</small></li>`);
}

const standalone = path.join(root, 'standalone');
for (const f of fs.readdirSync(standalone).filter((f) => f.endsWith('.html'))) {
  fs.copyFileSync(path.join(standalone, f), path.join(out, `standalone-${f}`));
  links.push(`<li><a href="standalone-${f}">${f}</a> <small>(e-mail autonome)</small></li>`);
}

fs.writeFileSync(
  path.join(out, 'index.html'),
  `<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Aperçus e-mails LEWEBMAX</title></head>
<body style="font-family:Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;color:#0d0d2b;">
<h1>Aperçus e-mails LEWEBMAX</h1><ul style="line-height:2">${links.join('\n')}</ul></body></html>\n`
);
console.log(`${links.length} aperçus générés dans previews/`);
