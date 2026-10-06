# Twilio Console / Integration Notes

This assignment is intentionally runnable without a live Twilio account. The local API simulates the values that Twilio would send to the application.

## 1. What Twilio does in the real system

For an incoming Voice call, Twilio sends an HTTP request to the configured webhook. Twilio documents incoming Voice calls as Voice webhooks, and the application responds with TwiML for interactive Voice behavior.

Official references:

- Voice webhooks: https://www.twilio.com/docs/usage/webhooks/voice-webhooks
- `<Gather>` for DTMF: https://www.twilio.com/docs/voice/twiml/gather
- Webhook security: https://www.twilio.com/docs/usage/webhooks/webhooks-security

## 2. Console walkthrough for the recording

In the Twilio Console, demonstrate the following areas if you have an account:

1. Open the Twilio Console.
2. Show the project/account area without exposing credentials.
3. Open the Phone Numbers section.
4. Select the Twilio phone number used for the demo.
5. Open its Voice configuration.
6. Show the incoming-call webhook configuration.
7. Explain that the webhook URL points to the public HTTPS endpoint that receives the incoming call.
8. Show the HTTP method configured for the webhook.
9. Explain that the local assignment uses `public/inbound_call.php` as the equivalent endpoint.
10. If using DTMF in a real call, explain that Twilio `<Gather>` can collect DTMF digits and send them to an action URL.

Do not display the Account SID/Auth Token or other secrets in the video.

## 3. Local development with a public URL

Twilio cannot normally reach `localhost` directly. For an actual Twilio-to-local test, expose the local PHP server through an HTTPS tunnel such as ngrok.

Example:

```bash
php -S localhost:8000 -t public
```

Then use a tunnel that exposes port 8000 and configure the resulting HTTPS URL in Twilio.

The exact tunnel command depends on the tunneling tool/account configuration.

Twilio's current webhook testing guidance notes that local development requires a public URL and describes using a tunnel such as ngrok.

## 4. Real IVR concept

The production flow would look like:

```text
Caller
   |
   v
Twilio Phone Number
   |
   v
POST /inbound_call.php
   |
   v
Create call session
   |
   v
Return TwiML
   |
   v
<Gather input="dtmf">
   |
   v
POST /menu_handler.php
   |
   v
Read call session state
   |
   +--> 1 View orders
   +--> 2 Place hold
   +--> 3 Voicemail
   +--> 4 Support
```

The assignment version replaces the TwiML/phone layer with curl/Postman requests.

## 5. Security for a production version

Twilio signs webhook requests using the `X-Twilio-Signature` header. Production code should validate this signature using Twilio's recommended SDK/helper rather than accepting arbitrary requests.

The assignment does not implement this because the requirement is a locally runnable simulation and no Twilio credential is required.

## 6. Why the assignment still demonstrates the important backend skills

Even without a live phone call, the core backend responsibilities are implemented:

- webhook-style request handling
- caller identification
- persistent call sessions
- stateful IVR workflow
- DTMF simulation
- order ownership validation
- transactional business operation
- voicemail persistence
- support routing
- audit logging
- invalid input/fallback handling

This keeps the solution focused on backend design rather than making the evaluator depend on a live telephony account.
