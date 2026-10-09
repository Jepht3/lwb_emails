#!/usr/bin/env node
// Assemble en-tête + corps + pied de chaque template WHMCS, pour chacun des thèmes,
// avec des données fictives, pour prévisualiser les e-mails dans un navigateur.
// Usage : node tools/build-previews.js  ->  previews/<thème>/*.html
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const mod = 'whmcs/modules/addons/lewebmax_emails';
const read = (p) => fs.readFileSync(path.join(root, p), 'utf8');

const sample = {
  client_first_name: 'Jephté',
  client_email: 'jephte@exemple.cd',
  whmcs_url: 'https://my.lewebmax.com/',
  signature: '',
  company_name: 'LE WEB MAX',
  client_name: 'Jephté Topde',
  reset_password_url: 'https://my.lewebmax.com/index.php?rp=/password/reset/redeem/abc123',
  flexpay_pay_link: 'https://my.lewebmax.com/modules/gateways/flexpay/pay.php?id=1042',
  invoice_amount_paid: '29,88 $ USD',
  invoice_status: 'Payée',
  invoice_html_contents:
    '<table width="100%" style="border-collapse:collapse"><tr><td>Hébergement Pro (09/10/2026 - 08/10/2027)</td><td align="right">29,88 $ USD</td></tr><tr><td><strong>Total</strong></td><td align="right"><strong>29,88 $ USD</strong></td></tr></table>',
  service_server_name: 'server.lewebmax.com',
  service_ns1_ip: '102.68.10.25',
  service_ns2_ip: '102.68.10.26',
  domain_reg_date: '09/10/2026',
  domain_days_until_nextdue: '30',
  domain_renewal_url: 'https://my.lewebmax.com/cart.php?a=renewals',
  domains_manage_url: 'https://my.lewebmax.com/clientarea.php?action=domains',
  ticket_link: '<a href="https://my.lewebmax.com/viewticket.php?tid=482913">https://my.lewebmax.com/viewticket.php?tid=482913</a>',
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

// Interprète le strict nécessaire de Smarty : {$var}, {if $var}…{else}…{/if}, {if isset($var)}…{/if}.
const fill = (html) =>
  html
    .replace(/\{if (?:isset\()?\$([a-z0-9_]+)\)?\}([\s\S]*?)(?:\{else\}([\s\S]*?))?\{\/if\}/g, (m, k, yes, no) =>
      sample[k] ? yes : no || ''
    )
    .replace(/\{\$([a-z0-9_]+)\}/g, (m, k) => (k in sample ? sample[k] : ''));
const stripComment = (html) => html.replace(/^\s*<!--[\s\S]*?-->\s*/, '');

const themes = JSON.parse(read(`${mod}/themes.json`));
const list = JSON.parse(read(`${mod}/templates.json`));

const out = path.join(root, 'previews');
fs.rmSync(out, { recursive: true, force: true });
fs.mkdirSync(out, { recursive: true });

const sections = [];
for (const theme of themes) {
  const dir = `${mod}/themes/${theme.slug}`;
  const css = read(`${dir}/email.css`);
  const header = stripComment(read(`${dir}/header.html`)).replace('[EmailCSS]', css);
  const footer = stripComment(read(`${dir}/footer.html`));
  fs.mkdirSync(path.join(out, theme.slug), { recursive: true });
  const links = [];
  for (const t of list) {
    const body = stripComment(read(`${mod}/templates/${t.fichier}`));
    const subject = fill(t.sujet);
    fs.writeFileSync(path.join(out, theme.slug, t.fichier), fill(header + body + footer));
    links.push(`<li><a href="${theme.slug}/${t.fichier}">${subject}</a> <small>(${t.template_whmcs})</small></li>`);
  }
  sections.push(`<h2>${theme.nom}</h2><p>${theme.description}</p><ul style="line-height:1.9">${links.join('\n')}</ul>`);
}

const standalone = path.join(root, 'standalone');
const extra = [];
for (const f of fs.readdirSync(standalone).filter((f) => f.endsWith('.html'))) {
  fs.copyFileSync(path.join(standalone, f), path.join(out, `standalone-${f}`));
  extra.push(`<li><a href="standalone-${f}">${f}</a></li>`);
}
sections.push(`<h2>E-mails autonomes</h2><ul>${extra.join('\n')}</ul>`);

fs.writeFileSync(
  path.join(out, 'index.html'),
  `<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Aperçus e-mails LEWEBMAX</title></head>
<body style="font-family:Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#0d0d2b;">
<h1>Aperçus e-mails LEWEBMAX</h1>${sections.join('\n')}</body></html>\n`
);
console.log(`${themes.length} thèmes × ${list.length} modèles générés dans previews/`);
