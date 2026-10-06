# Mini Phone Support System — PHP + MySQL + Twilio-style Webhooks

## 1. Overview

This project implements a small automated phone support / IVR backend using plain PHP and MySQL.

The application simulates a Twilio-style webhook flow without requiring a real phone call:

1. An inbound call webhook creates a call session.
2. The caller is identified by phone number.
3. Up to three recent orders are retrieved for known callers.
4. The caller sends simulated DTMF input.
5. The IVR supports:
   - `1` — View recent orders
   - `2` — Place an order on hold
   - `3` — Leave a voicemail
   - `4` — Talk to support
6. Every important action is logged.
7. Invalid input is retried up to three times and then falls back by ending the call.
8. Order ownership is validated before an order action is performed.

The implementation intentionally uses no framework so the evaluator can clearly see the PHP, SQL, workflow, validation, transaction, and logging logic.

---

## 2. Requirements

- PHP 8.1+ recommended
- MySQL 8+ or MariaDB 10.5+
- PDO MySQL extension enabled
- Postman, curl, or a browser
- Composer is not required

Recommended PHP version: PHP 8.2+.

---

## 3. Project Structure

```text
mini-phone-support-system/
├── config/
│   └── config.php
├── database/
│   └── schema.sql
├── public/
│   ├── bootstrap.php
│   ├── inbound_call.php
│   ├── menu_handler.php
│   ├── call_status.php
│   └── health.php
├── src/
│   ├── Database.php
│   ├── Response.php
│   ├── Request.php
│   ├── Logger.php
│   ├── PhoneNormalizer.php
│   ├── CallSessionService.php
│   ├── OrderService.php
│   └── IvrService.php
├── .gitignore
└── README.md
```

### Why this structure?

- `public/` contains the HTTP endpoints.
- `src/` contains business logic and database services.
- `config/` contains local configuration.
- `database/` contains schema and sample data.

The HTTP endpoint files are intentionally thin. Business logic is kept in service classes so the application is easier to test and maintain.

---

## 4. Database Setup

### Step 1 — Create/import the database

Run:

```bash
mysql -u root -p < database/schema.sql
```

Or import `database/schema.sql` using phpMyAdmin/MySQL Workbench.

The SQL file creates:

- `customers`
- `orders`
- `call_sessions`
- `call_logs`
- `order_logs`
- `voicemail_logs`
- `support_routes`

It also inserts 3 customers and 6 sample orders.

### Step 2 — Configure database credentials

Open:

```text
config/config.php
```

Default configuration:

```php
'host' => '127.0.0.1',
'port' => '3306',
'database' => 'phone_support',
'username' => 'root',
'password' => '',
```

Change the values according to your local MySQL installation.

Environment variables are also supported:

```text
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
APP_TIMEZONE
```

---

## 5. Run Locally

From the project root:

```bash
php -S localhost:8000 -t public
```

Then open:

```text
http://localhost:8000/health.php
```

Expected response:

```json
{
    "success": true,
    "status": "ok",
    "database": "connected"
}
```

---

# 6. API / IVR Flow

## Step A — Simulate an inbound call

Known sample caller:

```text
+919876543210
```

Request:

```bash
curl "http://localhost:8000/inbound_call.php?CallSid=CA-DEMO-001&From=%2B919876543210&To=%2B919000000000"
```

This creates a call session and identifies Amit Sharma.

The response contains:

- call ID
- CallSid
- caller phone
- customer
- recent orders
- current IVR state
- available menu

Example menu:

```text
1 = View recent orders
2 = Place an order on hold
3 = Leave a voicemail
4 = Talk to support
```

Save the returned numeric `call.id`, for example:

```text
1
```

---

## Step B — View recent orders

Send DTMF `1`:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=1&digit=1"
```

The state changes to:

```text
select_order_view
```

The API returns the caller's recent orders with selections:

```text
1 = ORD-1001
2 = ORD-1002
3 = ORD-1003
```

Select order 1:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=1&digit=1"
```

The selected order is validated against the caller before details are returned.

---

## Step C — Place an order on hold

Create another call:

```bash
curl "http://localhost:8000/inbound_call.php?CallSid=CA-DEMO-HOLD-001&From=%2B919876543210&To=%2B919000000000"
```

Assume the returned call ID is `2`.

Select menu option 2:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=2&digit=2"
```

Then select the first order:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=2&digit=1"
```

The system:

1. Validates that the order belongs to the caller.
2. Starts a database transaction.
3. Changes order status to `on_hold`.
4. Inserts an `order_logs` record.
5. Inserts a `call_logs` record.
6. Returns the updated order.
7. Returns the call to `main_menu`.

---

## Step D — Leave a voicemail

Create a new call:

```bash
curl "http://localhost:8000/inbound_call.php?CallSid=CA-DEMO-VM-001&From=%2B919812345678&To=%2B919000000000"
```

Assume call ID is `3`.

Select voicemail:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=3&digit=3"
```

Then simulate a recording:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=3&recording_id=RECORDING-DEMO-001"
```

Or:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=3&recording_url=https%3A%2F%2Fexample.com%2Frecordings%2Fdemo.mp3"
```

The recording identifier/URL is stored in `voicemail_logs` and the action is recorded in `call_logs`.

---

## Step E — Talk to support

Create a new call:

```bash
curl "http://localhost:8000/inbound_call.php?CallSid=CA-DEMO-SUPPORT-001&From=%2B919998887776&To=%2B919000000000"
```

Assume call ID is `4`.

Select option 4:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=4&digit=4"
```

The system creates a `support_routes` record:

```text
queue_name = support
routing_decision = routed_to_support_queue
```

No real transfer occurs because routing is explicitly a simulation requirement.

---

## Step F — Unknown caller

Use a number not present in the `customers` table:

```bash
curl "http://localhost:8000/inbound_call.php?CallSid=CA-DEMO-UNKNOWN-001&From=%2B919111111111&To=%2B919000000000"
```

Expected behavior:

- call session is created
- caller is marked unknown
- no customer is attached
- recent orders are empty
- order actions cannot be performed
- call/menu activity is still logged

---

## 7. Invalid Input / Retry Logic

The application allows a maximum of three invalid inputs.

Example:

```bash
curl "http://localhost:8000/menu_handler.php?call_id=1&digit=9"
```

The response contains:

```json
{
    "success": false,
    "message": "Invalid main menu option. Please choose 1, 2, 3, or 4.",
    "invalid_attempts": 1,
    "retry_remaining": 2
}
```

After the third invalid attempt:

```text
Call ends through fallback.
```

The fallback is also recorded in `call_logs`.

---

## 8. Call Status and Logs

To inspect a call:

```bash
curl "http://localhost:8000/call_status.php?call_id=1"
```

This returns the call session and all logs in chronological order.

Example events:

```text
call_started
menu_selection
order_selection_prompt
order_selected
order_hold
voicemail_saved
support_transfer
invalid_input
error
fallback
```

This makes the call traceable from start to finish.

---

# 9. Database Design

## customers

Stores caller/customer information.

Important fields:

- `id`
- `name`
- `phone`
- `email`

`phone` is unique because caller identification is based on phone number.

## orders

Stores customer orders.

Relationship:

```text
customers 1 ---- N orders
```

An index exists on:

```text
(customer_id, created_at)
```

This supports the "recent orders" query.

## call_sessions

Represents one inbound call.

Important fields:

- `call_sid`
- `caller_phone`
- `called_phone`
- `customer_id`
- `selected_order_id`
- `state`
- `invalid_attempts`
- `started_at`
- `ended_at`

The `state` field is the small IVR state machine.

Possible states include:

```text
main_menu
select_order_view
select_order_hold
voicemail
completed
```

## call_logs

Stores an audit trail of IVR activity.

Examples:

```text
call_started
menu_selection
order_selected
order_hold
voicemail_saved
support_transfer
invalid_input
error
fallback
```

## order_logs

Stores order-specific actions.

For example:

```text
order_id = 1
action = hold
comment = Order placed on hold through phone support IVR.
```

## voicemail_logs

Associates a simulated recording URL or identifier with a call.

## support_routes

Stores simulated routing decisions.

---

# 10. Security / Quality Decisions

### PDO prepared statements

All user-provided values are passed through prepared statements to reduce SQL injection risk.

### Order ownership validation

The system does not trust an order ID from the caller.

It verifies:

```sql
WHERE id = :id
AND customer_id = :customer_id
```

This prevents a caller from accessing another customer's order.

### Transactions

The hold operation uses a database transaction because the order update and order log should be treated as one operation.

### Centralized responses

`Response.php` keeps JSON response formatting consistent.

### Separation of concerns

Endpoint files receive requests, services handle business logic, and database access is isolated in service classes.

---

# 11. Assumptions

1. Twilio webhook fields are simulated locally using query parameters, form data, or JSON.
2. `CallSid` is treated as the unique external call identifier.
3. Phone numbers are normalized only for simple formatting. A production application should use E.164 validation/library support.
4. No actual telephone call is placed.
5. No actual Twilio recording is created. A recording URL or identifier is simulated and stored.
6. No real call transfer occurs. The application stores a support queue and routing decision.
7. Unknown callers can start a call session, but they cannot access customer order information.
8. The three most recent orders are determined by `created_at DESC`.
9. The current IVR state is stored in `call_sessions`.
10. Invalid input is retried up to three times before fallback.
11. Authentication for these public webhook endpoints is not implemented because the assignment focuses on the IVR workflow. In production, Twilio webhook signature validation should be added.
12. The JSON API response is used instead of TwiML to keep local testing simple. The same service layer can later be connected to Twilio TwiML responses.

---

# 12. Twilio Integration Approach

This project intentionally separates the IVR/business logic from the HTTP endpoints.

In a real Twilio deployment:

```text
Caller
   |
   v
Twilio phone number
   |
   | HTTP webhook
   v
/inbound_call.php
   |
   v
Create call session
   |
   v
IVR / business logic
   |
   +--> MySQL
   |
   +--> Twilio response / next webhook
```

The current implementation simulates the Twilio webhook values:

```text
CallSid
From
To
```

and DTMF:

```text
digit
```

A production integration would configure the Twilio phone number's Voice webhook to point to the application's public HTTPS endpoint and return TwiML such as `<Gather>` for DTMF collection.

---

# 13. Recommended Recorded Video Demonstration

The evaluation specifically mentions a recorded explanation, so use a short 7–10 minute demo.

## Part 1 — Introduction (30–60 sec)

Say:

> "I built a mini phone support IVR system using plain PHP, MySQL and a Twilio-style webhook simulation. The main goal was to keep the HTTP layer thin and put the workflow inside service classes."

Show the project structure.

## Part 2 — Database Design (1–2 min)

Open MySQL Workbench or phpMyAdmin.

Show:

```text
customers
orders
call_sessions
call_logs
order_logs
voicemail_logs
support_routes
```

Explain:

- customer → orders is one-to-many
- call session stores IVR state
- call logs provide auditability
- order logs record business actions
- voicemail and support routing are linked to calls

## Part 3 — Twilio Console Overview (1 min)

If you have a Twilio account, show:

- Twilio Console
- phone number
- Voice configuration
- incoming call webhook configuration
- webhook method
- request URL

Explain that this assignment uses a local Twilio-style simulation, while the production integration point is the same webhook endpoint.

Do not expose account credentials, auth tokens, or other secrets in the recording.

## Part 4 — Inbound Call (1 min)

Run:

```bash
curl "http://localhost:8000/inbound_call.php?CallSid=CA-DEMO-001&From=%2B919876543210&To=%2B919000000000"
```

Explain:

> "The system receives the CallSid, From and To fields, creates a call session, identifies the caller by phone number and retrieves up to three recent orders."

## Part 5 — View Order (1 min)

Run menu option 1, then select order 1.

Explain the order ownership validation.

## Part 6 — Place Order on Hold (1–2 min)

Create a new call.

Select:

```text
2
```

Then:

```text
1
```

Show the order status changing to:

```text
on_hold
```

Then show the corresponding `order_logs` and `call_logs` records.

Mention that this action uses a database transaction.

## Part 7 — Voicemail (1 min)

Select:

```text
3
```

Then send:

```text
recording_id=RECORDING-DEMO-001
```

Show the `voicemail_logs` row and call log.

## Part 8 — Support Routing (30–60 sec)

Select:

```text
4
```

Show:

```text
queue_name = support
routing_decision = routed_to_support_queue
```

Explain that this is intentionally simulated.

## Part 9 — Error Handling (30–60 sec)

Send:

```text
digit=9
```

Show retry count.

Explain that after three invalid attempts the system falls back and logs the fallback.

## Part 10 — Closing (30 sec)

Say:

> "The main design focus was separation of concerns, prepared SQL statements, order ownership validation, transactional order updates, state-based IVR handling and traceable logging. The application can later be connected to real Twilio webhooks without changing the core business logic."

---

# 14. Suggested Evaluation Talking Points

If the interviewer asks "Why did you design it this way?", explain:

### Why store IVR state?

Because a phone conversation consists of multiple HTTP webhook requests. The server must know what the caller is currently expected to provide.

### Why store CallSid?

It is the external call identifier and allows multiple webhook requests to be associated with the same call.

### Why use call_sessions?

It provides the stateful context needed for a stateless HTTP request/response flow.

### Why use prepared statements?

To safely handle request data and prevent SQL injection.

### Why validate order ownership?

A caller must never be able to access or modify an order belonging to another customer.

### Why use a transaction for hold?

The order status update and its audit record represent one business action. The transaction prevents a partial update.

### Why separate call_logs and order_logs?

`call_logs` answers "what happened during this phone call?", while `order_logs` answers "what happened to this order?"

---

# 15. Possible Future Improvements

If this were productionized, I would add:

- Twilio webhook signature validation
- E.164 phone-number validation
- authentication/authorization for administrative APIs
- TwiML response generation
- real `<Gather>` DTMF handling
- real Twilio recording callbacks
- actual queue/agent integration
- structured application logging
- automated PHPUnit tests
- environment-based secrets
- rate limiting
- encryption/tokenization for sensitive phone data
- monitoring and alerting
- Docker-based local setup
- OpenAPI documentation

---

# 16. Submission Checklist

Before submitting:

- [ ] Source code included
- [ ] `database/schema.sql` included
- [ ] Sample customers included
- [ ] Sample orders included
- [ ] README included
- [ ] PHP runs locally
- [ ] Database imports successfully
- [ ] Inbound call tested
- [ ] Caller identification tested
- [ ] View orders tested
- [ ] Order ownership validation tested
- [ ] Order hold tested
- [ ] Voicemail tested
- [ ] Support routing tested
- [ ] Invalid input tested
- [ ] Call logs checked
- [ ] Order logs checked
- [ ] No passwords/API secrets committed
- [ ] Recorded demo prepared
- [ ] Twilio Console overview shown if available

# 17. Local Configuration on macOS

This project was developed and tested locally on macOS using **MAMP MySQL** and PHP's built-in development server.

## 17.1 Prerequisites

Install or have available:

* macOS
* MAMP
* PHP 8.1+
* MySQL/MariaDB
* Terminal
* Postman or curl

## 17.2 Start MAMP

Open:

```text
/Applications/MAMP
```

Start MAMP and click:

```text
Start Servers
```

Verify that both Apache and MySQL are running.

This project uses the MAMP MySQL Unix socket rather than TCP because the local MAMP MySQL instance is exposed through:

```text
/Applications/MAMP/tmp/mysql/mysql.sock
```

## 17.3 Verify MySQL

Run:

```bash
/Applications/MAMP/Library/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p
```

Enter the local MAMP MySQL password.

Then verify:

```sql
SELECT VERSION();
```

## 17.4 Create and Import the Database

From the project root:

```bash
cd /Users/<your-user>/Desktop/Projects/mini-phone-support-system
```

Import the provided SQL file:

```bash
/Applications/MAMP/Library/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p \
  < database/schema.sql
```

The SQL file creates the `phone_support` database and inserts sample customers and orders.

Verify:

```bash
/Applications/MAMP/Library/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p \
  -e "SHOW DATABASES;"
```

Then:

```bash
/Applications/MAMP/Library/bin/mysql \
  --socket=/Applications/MAMP/tmp/mysql/mysql.sock \
  -u root -p \
  phone_support \
  -e "SHOW TABLES;"
```

Expected tables:

```text
call_logs
call_sessions
customers
order_logs
orders
support_routes
voicemail_logs
```

## 17.5 Configure PHP Database Connection

For the MAMP environment, `config/config.php` uses:

```php
'db' => [
    'socket' => '/Applications/MAMP/tmp/mysql/mysql.sock',
    'database' => 'phone_support',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8mb4',
],
```

`src/Database.php` creates the PDO connection using the Unix socket:

```php
$dsn = sprintf(
    'mysql:unix_socket=%s;dbname=%s;charset=%s',
    $db['socket'],
    $db['database'],
    $db['charset']
);
```

For another MySQL installation, the connection can be changed to a normal host/port configuration such as `127.0.0.1:3306`.

## 17.6 Start the PHP Application

From the project root:

```bash
php -S localhost:8000 -t public
```

Keep this Terminal window running.

The application is available at:

```text
http://localhost:8000
```

## 17.7 Verify the Application

Open another Terminal and run:

```bash
curl http://localhost:8000/health.php
```

Expected:

```json
{
    "success": true,
    "status": "ok",
    "database": "connected"
}
```

## 17.8 Local Testing

The application does not require a live telephone call for local testing.

Twilio-style webhook parameters are simulated using curl/Postman:

```text
CallSid
From
To
digit
recording_id
recording_url
```

Example:

```bash
curl "http://localhost:8000/inbound_call.php?CallSid=CA-DEMO-001&From=%2B919876543210&To=%2B919000000000"
```

The returne
