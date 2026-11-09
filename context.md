I am trying to build an operational centre for any subscription-based academy. Football is the first pilot use case, but sports, educational, language and skills academies must work without custom application logic.

MODULES

Module Key capabilities Priority
Executive dashboard Players, revenue, outstanding fees, attendance, expenses, branch comparisons Essential
Player management Profiles, age groups, status, documents, enrolment and transfers Essential
Parents & guardians Contacts, linked children, emergency information, communication Essential
Branch management Oniwaya, Gbagada, Alimosho; schedules, capacity and enrolments Essential
Fees & payments Annual fees, instalments, receipts, balances, payment reminders Essential
Attendance Coach check-in, absence tracking, attendance reports Essential
Expenses & finance Equipment, facilities, staff expenses, income and profitability Essential
Coaches & staff Assignments, permissions, schedules and basic staff records Essential
Training & development Drills, assessments, skill progression and coach remarks Growth
Parent portal Child progress, attendance, payments and announcements Growth
Referrals 10% rewards, referral attribution, eligibility and payouts Growth
Store & inventory Jerseys, boots, bibs, stock, orders and sales Growth
Sponsorship & impact Sponsors, donations, scholarships and community projects Growth
Reports & analytics Revenue trends, retention, outstanding balances, branch performance Growth
Website administration Manage website enquiries, content, gallery, news and events Growth

It should support:
Features that would make KCFA's system particularly valuable
Annual fee tracking: Because KCFA charges ₦150,000 per year, the system should track each player's enrolment anniversary, payments, balances, discounts, scholarships and renewal dates. It should also allow instalments if management approves them.

Branch-level financial performance: Management should see whether Oniwaya, Gbagada or Alimosho is generating enough income to cover its operational expenses.

Player development records: Coaches should be able to assess ball control, passing, shooting, fitness, teamwork and discipline using age-appropriate criteria. Parents could receive periodic progress reports.

Retention monitoring: Identify players whose fees are due, whose attendance is declining or whose membership is approaching renewal.

Impact reporting: Since community development is central to KCFA's identity, the platform should track sanitation activities, education initiatives, donated equipment, scholarships and sponsors' contributions.

Player performance assessments

Parent and guardian access.
Automated payment reminder
Referral reward administration
Sponsorship and scholarship management
Equipment and merchandise inventory
Community impact reporting
Advanced financial analytics
Franchise and multi-location management

Scholarships..can be full for a year, for two year and more.. Can be for a part payment reduction..in amount or percentage.

Build it as a multitenant, and a tenant can have multiple branches, but a single comprehensive dashboard and can choose to see what is happening in each branch.

Expose an API that different website owners can use to subscribe for their student registration, should cover student biodata, health information, parent information.

Create a status/page for prospects who signedup but not yet paid.

Then create programmes...so the fees breakdown are tied those programmes.

When an academy logs in, let them be able to see their prospects, and main students/players.

I want to be able to use it for anybody running subscription based academy, whether educational, football, language academy etc.

The goal is to have a final tool that the owner of an academy can use to manage its business. support all the features here and support creation of plans and ability to turn on a module for plan or not. e.g refferal, Expenses etc.

Remember student/players can have a class, under 10, under 17 or class !, class 2.

support coupons, multicurrency,part payment, and ability to post payment from admin. and also integrate paystack API to log payment and verification of payment internally verifying reference, and also ability to requery

Layer Recommended technology
Backend Laravel 13, PHP 8.4
Frontend React, TypeScript, Inertia.js
UI Tailwind CSS, shadcn/ui
Database MySQL 8
Authentication Laravel session authentication
Roles and permissions Spatie Laravel Permission
Background jobs Laravel Queues
Payments Paystack
Email Mailgun or another transactional email service
File storage S3-compatible object storage
Hosting DigitalOcean, Ubuntu LTS, Nginx
Monitoring Laravel logs, uptime monitoring and error tracking

4. The stack I would actually use
   Layer My selection
   Backend Laravel
   Frontend React 19 + TypeScript
   Laravel–React bridge Inertia.js
   Styling Tailwind CSS
   Component library shadcn/ui
   Forms Inertia forms, Zod where appropriate
   Data tables TanStack Table
   Charts Recharts
   Icons Lucide
   Database MySQL
   Queue and cache Redis
   Authentication Laravel session authentication
   Authorization Laravel policies + Spatie Permission

5. Build order
6. Foundation: Authentication, academies, branches, users, roles, subscriptions and tenant isolation.
7. Operations: Players, guardians, coaches, attendance and schedules.
8. Finance: Fees, payment records, expenses, receipts and dashboards.
9. Growth: Parent portal, player development, referrals and notifications.
10. Enterprise: Sponsorships, scholarships, merchandise, community impact and advanced analytics.

Please when handling payment, lock payment amount for individual because sometimes the payment amount may change from time to time so we don't break the application.

Don;t forget the super admin access where i can choose to select any academy and administer them.. but additionally i can select a plan for them or turn on/off modules.

i created a database called academy. thats what you will use.

DB_DATABASE=academy
DB_USERNAME=<local database user>
DB_PASSWORD=<local secret; keep in .env only>

Test with this paystack keys:

use storage on the local server...not AWS. i am going to use digital ocean, i am not paying for S3 for now.

use this paystack keys for test

PAYSTACK_PUBLIC_KEY=<test public key; keep in .env only>
PAYSTACK_SECRET_KEY=<test secret key; keep in .env only>

COLOUR THEME

Recommended website color usage
Website element Color
Header / Navbar Deep Navy #071D49
Main headings Deep Navy #071D49
Primary CTA buttons Orange #FF6900
Links / Secondary buttons Royal Blue #0878D1
Success / Progress indicators Teal #12B8B0
Page background White #FFFFFF
Alternate sections Light Blue Gray #F3F7FB
