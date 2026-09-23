# Connecting Microsoft 365 for booking

What this gives you: the chat only offers times the salesperson is genuinely free, the meeting
lands in their Outlook/Teams calendar with the visitor invited, and Microsoft puts a **Teams
link** on it. Reschedules move that meeting; cancellations cancel it.

Without it nothing breaks — booking works exactly as before, from the availability grid, with an
.ics link in the confirmation email.

## 1. Register the app (your Azure tenant, about ten minutes)

Azure portal → **Microsoft Entra ID** → **App registrations** → **New registration**.

- **Name:** anything — "Piotrack booking" is clear enough.
- **Supported account types:** "Accounts in this organizational directory only" for your own
  tenant. Choose the multi-tenant option only if other organisations will connect their own
  Microsoft 365 to your Piotrack.
- **Redirect URI:** Web →
  `https://YOUR-PIOTRACK-DOMAIN/settings/integrations/oauth/microsoft_365/callback`

Then, inside the registration:

- **Certificates & secrets** → **New client secret**. Copy the *value* immediately; Azure never
  shows it again. Note the expiry — the connection stops working the day it lapses.
- **API permissions** → **Microsoft Graph** → **Delegated permissions**, add:
  `offline_access`, `openid`, `email`, `User.Read`, `Calendars.ReadWrite`,
  `Calendars.Read.Shared`, `OnlineMeetings.ReadWrite`. Then **Grant admin consent** so nobody is
  prompted individually.

`Calendars.Read.Shared` is what lets the chat check a colleague's free/busy, not only the
connected account's own diary.

## 2. Put the credentials on the server

In `.env`:

```
MICROSOFT_CLIENT_ID=the application (client) id
MICROSOFT_CLIENT_SECRET=the secret value you copied
MICROSOFT_TENANT=your directory (tenant) id, or "common" for any work account
```

Then `php artisan config:clear`. Until these exist the connector shows as "coming soon" on the
integrations page, which is the intended behaviour rather than an error.

## 3. Connect

Settings → **Integrations** → **Microsoft 365 / Outlook** → Connect. Sign in as the account whose
calendar the meetings should live in — usually a shared sales mailbox or the person who runs
appointments. Piotrack remembers which account that is and shows it on the connection.

## What happens afterwards

- **Offering times:** before the chat shows free slots it asks Graph for the busy times of the
  booking page's owner and the connected account, and drops any slot that clashes. Answers are
  cached for a minute, so a chat does not hammer Graph.
- **Booking:** the meeting is created on the connected calendar, the visitor is invited, and
  `isOnlineMeeting` asks Microsoft for a Teams link. The link is stored on the booking and sent
  in the confirmation email.
- **Changes:** rescheduling moves the meeting; cancelling cancels it, so attendees are told.

## When something goes wrong

Every call fails quietly by design: no connection, an expired consent or a Graph outage all leave
booking working exactly as it did before, and the reason is written to `last_error` on the
connection (visible on the integrations page) rather than shown to a visitor. If bookings stop
appearing in Outlook, look there first — an expired client secret is the usual cause.

Access tokens are refreshed automatically. If the refresh token itself is rejected (consent
revoked, secret rotated), reconnect from the integrations page.
