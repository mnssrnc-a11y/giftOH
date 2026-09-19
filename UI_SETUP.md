# Updating the shared UI

After pulling main, run these commands from the project folder:

```sh
npm ci
npm run build
php artisan view:clear
```

Refresh the browser with Ctrl+F5. Compiled assets in public/build are ignored by Git and must be rebuilt on each computer. If using npm run dev instead, keep the Vite process running.

Pages:
- /admin#settings: admin workspace settings
- /superadmin: superadmin preview
- /dashboarduser: community feed preview
- /user: user profile
- /settings: user settings preview, with links to the existing account edit/password forms
- /groups, /fundraisers, /request-status, /activity: sample community flows
- /notifications: existing live notifications

The shared navigation uses the newer user layout. Existing backend controllers, fund-request fields, notification handlers, and admin logout remain in place. Community feed and newly added flow pages contain clearly marked sample data; preview actions do not persist account or funding changes.
