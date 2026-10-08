# Deploying Gift of Hope online (Render free plan)

The site runs on [Render](https://render.com)'s free plan as a Docker web service, built from the
GitHub repo `mnssrnc-a11y/giftOH`. Nothing here needs a credit card.

Render's free plan has three limits, and the app is set up around them:

| Free-plan limit | What the app does instead |
| --- | --- |
| Outgoing SMTP (ports 25/465/587) is blocked, so Gmail SMTP cannot send codes | `MAIL_MAILER=brevo` sends mail through Brevo's HTTPS API (free: 300 emails/day) |
| The disk is wiped on every restart, redeploy and sleep, so uploads would vanish | `FILES_DRIVER=firebase` keeps uploads in your Firebase Realtime Database (`file_store/…`) |
| No files can be uploaded to the server, so there's no `firebase_credentials.json` | `FIREBASE_CREDENTIALS_JSON` holds the key itself as an environment variable |

On your own PC nothing changes: XAMPP keeps using Gmail SMTP and `storage/app`.

---

## 1. Set up Brevo (email), about 5 minutes

1. Sign up at **brevo.com** (free, no card).
2. **Senders, domains & IPs → Senders → Add a sender**: enter the Gmail address that should send the
   verification codes (for example the one in `MAIL_FROM_ADDRESS`). Open the confirmation email
   Brevo sends to that inbox and click the link.
3. **SMTP & API → API keys → Generate a new API key**. Copy it (it starts with `xkeysib-`; an SMTP
   key starting with `xsmtpsib-` does not work with the API).
4. Save it in the app: sign in as the super admin → **System settings → Verification email
   settings**, paste the key and the sender, and enter the code that is emailed to the sender. The
   key is stored encrypted in Firebase (with `APP_KEY`), so it works on your PC and on Render, and
   it takes priority over `BREVO_API_KEY`. Passing `--brevo-key` in step 4 below is optional.

## 2. Push the code to GitHub

From the project folder (VS Code's Source Control panel works too):

```sh
git add -A
git commit -m "Deploy to Render free plan"
git push origin main
```

## 3. Copy the files you already uploaded into Firebase

Documents and profile photos uploaded on your PC are in `storage/app`. Copy them into Firebase so
the online site can open them too (run on the PC that has them):

```sh
php artisan files:push-to-firebase --dry-run   # shows what will be copied
php artisan files:push-to-firebase
```

Run it again any time; files that are already there are skipped.

## 4. Generate the Render environment variables

```sh
php artisan deploy:render-env --url=https://giftofhope.onrender.com --brevo-key=xkeysib-PASTE-YOURS
```

This writes `storage/app/firebase/render.env`: your current `.env` settings plus production values
(Brevo, Firebase file storage, cookie sessions, HTTPS) and your Firebase key encoded as
`FIREBASE_CREDENTIALS_JSON`. **It contains secrets.** Git and the Docker image both ignore it.
Delete it once it's pasted into Render.

## 5. Create the service on Render

1. Sign in at **dashboard.render.com** with GitHub and allow access to the `giftOH` repo.
2. **New → Blueprint**, pick the repo. Render reads `render.yaml` and proposes a free web service
   called `giftofhope` in Singapore. It asks for a few secret values. You can copy them from
   `render.env` now, or leave them blank and click **Apply**. If you leave them blank, the first
   deploy fails with "APP_KEY is not set" until you do step 3, which is expected.
3. Open the service → **Environment** → **Add from .env**, paste the whole `render.env` file,
   then **Save, rebuild and deploy**.
4. Wait for the deploy to finish (the first build takes about 5–10 minutes). The **Logs** tab shows
   progress; the site is live when the health check on `/up` passes.

If Render gave you a different address (for example `giftofhope-abcd.onrender.com` because the name
was taken), change `APP_URL` in **Environment** to that address and save.

## 6. Check it works

- [ ] Home page, About, Login and Register open over `https://`
- [ ] Register a test account: the 6-digit code arrives (check Spam the first time)
- [ ] Sign in (login code), open Settings, upload a profile picture, reload: it's still there
- [ ] Submit a fund request with documents, then open them from the admin side
- [ ] Admin → IoT monitor shows the Smart Box status
- [ ] Super admin → System settings shows **Brevo API** as the mail server

## Updating the site

Every `git push` to `main` redeploys automatically.

## Good to know

- **Sleep:** a free service sleeps after 15 minutes without visitors. The first visit after that
  takes about a minute while it wakes up. Before a demo or defense, open the site a few minutes
  early. To keep it awake, a free monitor such as cron-job.org or UptimeRobot can request
  `https://<your-site>/up` every 10 minutes (Render's 750 free hours a month cover one service
  running all month).
- **Logins:** sessions are stored in an encrypted cookie, so a restart doesn't sign anyone out.
- **Email:** Brevo's free plan sends 300 emails a day. Codes sent from a Gmail address through
  Brevo can land in Spam at first; marking one "Not spam" helps.
- **Files:** Firebase's free (Spark) Realtime Database holds 1 GB and serves 10 GB a month. Uploads
  are limited to 10 MB each, which fits comfortably for a capstone-sized deployment. The
  `file_store` node is private: the database rules deny it to everyone except the server.
- **Files online vs. on your PC:** files uploaded on the live site live in Firebase. To see them in
  your local copy too, set `FILES_DRIVER=firebase` in your local `.env`.
- **Malware scan:** Windows Defender doesn't exist on a Linux server, so online uploads get the
  built-in checks (`UPLOAD_SCAN_ENGINES=heuristic`): blocked extensions, hidden scripts, PDF
  JavaScript, disguised files and the EICAR test file.
- **Scheduled jobs** (monthly price update, daily file re-scan) run inside the container, but only
  while it's awake. The super admin's "Update prices" button still works any time.
- **Super admin → email sender:** online, a new sender must first be verified in Brevo (step 1.2).
  No app password is needed, and the choice is saved in Firebase (`system_settings/mail_sender`).

## Troubleshooting

| What you see | Fix |
| --- | --- |
| Deploy log: `APP_KEY is not set` | Paste `render.env` again (step 5.3). `APP_KEY` must start with `base64:` |
| Error pages mentioning Firebase credentials | `FIREBASE_CREDENTIALS_JSON` is missing or cut off. Re-run step 4 and paste again |
| "Brevo refused this sender" / codes never arrive | Verify the sender in Brevo (step 1.2) and check `BREVO_API_KEY` |
| Pages load without styling, or forms say "insecure" | `APP_URL` must be the exact `https://…onrender.com` address |
| An old document shows "not found" online | Run `php artisan files:push-to-firebase` on the PC that has it |

## Running the same image elsewhere

The `Dockerfile` works on any Docker host (Railway, Fly.io, a VPS). Set the same environment
variables. On a host with a persistent disk and open SMTP you can keep `FILES_DRIVER=local` and
`MAIL_MAILER=smtp` instead.
