# Sri Lanka Expert · Version 12.4.2

Responsive German/English tour planning website built with PHP 8.1+ and MySQL 8/MariaDB 10.4+.

## Included

- German default language and English language switch
- Responsive public website
- Rebranded public and admin interface for Sri Lanka Expert by Raonex GmbH
- Mobile-first three-step tour planner with a full-screen searchable destination selector
- Homepage destination explorer with featured places, search and experience filters
- Public ready-made tour catalogue with German/English category filters
- Ten editable starter tours: winter, summer, sport, culture, discovery, backpacker, Ayurveda plus Sri Lanka, family and luxury
- Detailed ready-made tour pages with day-by-day route, included services, seasonal prices, Google map and print layout
- One-click customisation that copies a tour into the tailor-made planner without changing the original template
- Admin tour-template editor with route ordering, hotels, services, 0-night stops, duplication, drafts and publishing
- Editable company contact details and social-media links in Admin → Settings
- Four customer paths: tailor-made Sri Lanka tour, Beach Vacation, Ayurveda Hotel stay and Maldives resort holiday
- Separate bilingual Maldives resort catalogue and resort-detail pages
- 9 editable Maldives atoll regions and 25 editable resort starter entries
- One-resort Maldives planner with rooms, meals, airport transfer, excursions and activities
- Private printable Maldives proposal with every selected service and full price breakdown
- Editable global sales-price adjustment percentage under Admin → Settings
- Base prices remain unchanged; the percentage is applied to public prices and new calculations
- Submitted enquiries save the percentage used, so later changes never alter an existing quotation
- Customer self-registration, secure customer login and editable profile
- Customer dashboard for enquiries, bookings, balances, payments and printable documents
- Admin customer-account management with activation and password reset
- Enquiry-to-booking conversion with unique booking references
- Booking statuses, travel dates, deposits, outstanding balances and manual payment history
- B2B agency management with company data, portal login and editable commission percentage
- B2B agent portal for the agency's own client enquiries and bookings
- Agent commission snapshots per booking, protected from later agency-setting changes
- Single-hotel planner that permits exactly one hotel plus optional services
- Beach-hotel filtering and editable Beach/Ayurveda availability per hotel
- Ayurveda stays with editable 7, 14, 21 and 28-night options and required all-inclusive
- Double-room formula: room rate × rooms × nights
- Adult extra bed: editable percentage of the room rate, initially 30%
- Child pricing: editable percentage, initially 50% for children up to 12
- Ayurveda packages and transfers stored as separately calculated services
- Additional-service price units: per person, per person/night or per booking
- Private printable hotel-stay proposal with complete price breakdown
- Customer tour builder
- Destination and route selection
- Interactive Google map with ordered driving route, markers, distance and drive time
- Clickable Google map in the destination editor for setting coordinates
- Printable live route preview and professional saved itinerary/PDF view
- 32 active Sri Lanka destination hubs plus 12 editable specialist drafts
- One accommodation selection per destination
- Double-room pricing per room and night, with an adjustable room count
- Automatic minimum room count based on two travellers per double room
- Hotel-specific meal-plan selection: room only, breakfast, half board, full board or all inclusive
- Editable meal-plan names, availability and supplements per person/night in the admin
- Adjustable number of nights for every selected destination
- The same destination can be used more than once, for example Negombo as the first and final stop
- Repeated destinations are independent stops with their own hotel, meal plan, services and nights
- Zero-night transit/day stops are supported; a repeated return stop starts at 0 nights
- Multiple sights, activities and shop stops
- Live price estimate using `room rate × rooms × nights`; meal supplements remain per person/night
- Planner starts with no destination and a zero estimate
- Destination guide prices are informational and are not charged automatically
- Total nights equal the exact sum of selected nights; no extra arrival day is added
- Editable vehicle fleet with automatic capacity recommendation for 1–50 travellers
- Vehicle choice is saved with the enquiry and shown in the admin and printable itinerary
- Every selected accommodation, meal plan, sight, activity and shop is shown under its destination in both print views
- Editable vehicle daily rates with driver and, for cars up to 3 travellers, without driver/self-drive
- Editable with-driver kilometre rate, initially €0.30/km
- Vehicle cost is calculated from vehicle days, daily rate and route distance and included in the estimate
- Enquiry form stored in MySQL
- Password-protected administration
- Enquiry workflow: new, contacted, quoted, confirmed, cancelled, archived
- Admin tour builder for customer enquiries
- Admin Beach, Ayurveda and Maldives stay builders
- Destination and catalogue editing in German and English
- Image upload for destinations and catalogue items
- Web installation wizard
- Prepared SQL statements, password hashing, CSRF protection and protected upload folder

## Local installation with XAMPP on Windows

1. Install XAMPP with PHP 8.1 or newer.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Extract the project folder to:

   C:\xampp\htdocs\sri-lanka-tailor-tours

4. Open:

   http://localhost/sri-lanka-tailor-tours/install.php

5. Use these normal XAMPP database values:

   - Host: 127.0.0.1
   - Port: 3306
   - Database: srilanka_tours
   - User: root
   - Password: leave empty unless you changed it

6. Enter your administrator email and a password with at least 10 characters.
7. Click Install website.
8. Sign in at:

   http://localhost/sri-lanka-tailor-tours/admin/login.php

The installer creates the database, all tables, the administrator account and sample Sri Lanka content. The automatic Version 6 migration gives every installed destination editable starter coverage with two accommodations, two sights, two activities and one local-shop stop. These clearly marked test entries must be replaced or edited before publication.

## Move the tested website to Plesk

1. Create a MySQL database and database user in Plesk.
2. Upload all project files to the domain document root.
3. Before uploading a locally installed copy, do not upload:

   - config.local.php
   - storage/install.lock

4. Open https://your-domain.example/install.php.
5. Enter the Plesk database host, name, user and password.
6. Set the website URL to the final HTTPS domain.
7. Complete installation and sign in.

If you intentionally upload an already installed database separately, update config.local.php with the server database credentials and final base_url.

## Required PHP extensions

- PDO
- pdo_mysql
- JSON
- fileinfo
- GD is recommended but not required

## Folder permissions

The web server must be able to write:

- the project root during installation, so config.local.php can be created
- storage/
- uploads/

Typical Linux/Plesk permissions are directories 755 or 775 and files 644, depending on the hosting user.

After installation, install.php is locked by storage/install.lock. The .htaccess file blocks direct access to configuration, database and internal include files when Apache is used.

## Configuration

The installer creates config.local.php. A safe template is available as config.example.php.

Important settings:

- app.base_url: complete URL without a trailing slash
- app.timezone: default Europe/Berlin
- database host, port, name, user and password

Do not commit or publicly share config.local.php.

## Administration

- Overview: /admin/index.php
- Enquiries: /admin/requests.php
- Ready-made tours: /admin/tours.php
- Customer accounts: /admin/customers.php
- Bookings and payments: /admin/bookings.php
- B2B agencies: /admin/agents.php
- Destinations and content: /admin/catalog.php
- Vehicle fleet: /admin/vehicles.php
- Website, company, social-media and Google Maps settings: /admin/settings.php
- Customer journey builder: /plan.php?mode=admin
- Beach-stay builder: /stay.php?type=beach&mode=admin
- Ayurveda-stay builder: /stay.php?type=ayurveda&mode=admin
- Maldives catalogue: /maldives.php
- Maldives-stay builder: /stay.php?type=maldives&mode=admin

Use the content area to add destinations, accommodation, sights, activities, shops and additional services in German and English.

Ready-made routes are managed under **Admin → Ready-made tours**. Each template has bilingual sales text, an ordered itinerary, editable nights, one preselected hotel per stop, multiple included sights/activities/shops/services, seasonal prices and publication controls. **Duplicate** creates a private draft that can be adapted without changing the source. A public customer who clicks **Customise this tour** receives an independent planner copy; later changes to the master template do not alter the submitted enquiry.

Each accommodation can be enabled for Beach Vacation, Ayurveda stays, Maldives stays, or any combination. The same edit form controls standard occupancy, maximum occupancy with an extra bed, the adult extra-bed percentage, child percentage, child age limit and allowed Ayurveda night packages. Sights, activities, shops and Additional Services can also be enabled separately for each single-hotel area.

Version 12.1 adds 25 editable Maldives resort starters across 9 atolls. Their initial room, meal and transfer prices are guide/test values and must be checked against current contracts before publication. Resort names, descriptions, availability flags, images, room prices, meal supplements and optional services are all editable under **Admin → Travel components**. Maldives destinations never appear in the Sri Lanka driving-route planner.

Version 12.2 adds one global **sales-price adjustment percentage** under **Admin → Settings**, initially set to `10 %`. It turns a €1,000 base price into a €1,100 customer price. The adjustment applies to new tours, hotel stays, meal supplements, services and vehicle rates, while all catalogue and fleet base prices remain unchanged. Each submitted enquiry stores the percentage and calculated adjustment amount as a snapshot; changing the setting later affects only new prices and enquiries.

Version 12.3 added customer accounts, booking management and B2B agency management. Customers can register through **Customer account**, sign in, update their profile and view every linked enquiry, booking, payment and private print document. Registration automatically links earlier unassigned enquiries using the same email address. Administrators manage customer access under **Admin → Customer accounts** and convert an accepted enquiry into a booking from its detail page.

## Version 12.4: vehicle images, guides and Maldives visuals

- Each fleet record accepts a JPG, PNG or WEBP image in **Admin → Vehicle fleet**.
- The selected vehicle image appears in the planner and is snapshotted into the submitted printable itinerary.
- A bilingual public guide application is available through the footer under **Guide registration**.
- Guide applications include contact/address data, licence and tourism registration, languages, regions, specialisations, driver-guide status, experience, daily rate, profile text, emergency contact, profile photo and protected supporting documents.
- Licence, identity and insurance files are stored below `storage/guide-documents` and can only be served through the authenticated admin document endpoint.
- Administrators review applications under **Admin → Tour guides**, set status, activate profiles, edit rates and open protected documents.
- Only approved and active guides appear as an optional planner selection. Their daily rate is calculated for the tour/vehicle days and appears in the saved enquiry and printable itinerary.
- Nine Maldives atolls and existing Maldives resorts receive original local starter visuals only when their image field is empty. Existing uploaded images are never replaced.
- The packaged SVG visuals are design placeholders, not photographs of a named resort. Replace them with your own licensed partner photography before publication.

### Version 12.4.1 route correction

- Bandaranaike International Airport is included as an arrival/departure route point with **0 nights** by default.
- Every route stop can be reduced to 0 nights.
- Selected places now have a clearly labelled **Add again at route end** button.
- Searching for a place already used in the route shows it again with an **Already selected · add again** message.
- Repeated stops remain independent, so an arrival stay and a later departure stay can have different nights, hotels and services.

### Version 12.4.2 route status colours

- A destination selected once is highlighted in light green.
- Every overnight occurrence of a destination used twice or more is highlighted in stronger green.
- A route stop with 0 nights is highlighted in orange and takes priority over the repeat colour.
- A bilingual colour legend is shown above the route search on desktop and mobile.

Bookings have a unique `BK-...` reference, travel dates, total amount, agreed deposit, payment due date, status, paid amount and outstanding balance. Administrators record bank transfers, cards, PayPal, cash or other payments. The customer dashboard immediately reflects the updated balance and payment history.

B2B agencies are created only by an administrator under **Admin → B2B agencies**; no sample agency or login is inserted automatically. Every agency receives a separate portal login and an editable commission percentage. Enquiries sent while signed in as an agent are assigned to that agency. When converted into a booking, the current agency commission is stored as a snapshot, so later changes to the agency percentage do not rewrite older bookings.

Single-hotel pricing is recalculated on the server before an enquiry is stored:

`room rate × rooms × nights + extra beds + meals per person/night + selected services`

The standard setup includes two guests in a double room. An additional adult bed costs 30% of the room rate. A child up to age 12 costs 50% of the corresponding adult extra-bed, meal and person-based service charge. Older children must be entered as adults. Ayurveda requires all-inclusive; an Ayurveda treatment package remains a separate service and is not hidden inside the room or meal price.

For an accommodation, the price field is the double-room rate per room and night. The edit form also contains its meal plans. Each plan can be activated or deactivated, renamed in German and English, and given an individual supplement per person and night. In the public planner the customer first chooses the destination, then one hotel, the room count, its meal plan, sights and other experiences. Nights can be changed between 0 and 14 for every tour stop. Total nights are the exact sum of the selected nights. The ＋ button beside an existing route stop adds the same destination again at the end of the route; this return stop starts with 0 nights and can be configured independently.

The vehicle fleet is managed under **Admin → Vehicle fleet**. The default capacities are Car up to 3, Mini Van up to 4, Van up to 7, Mini Bus up to 20 and Bus up to 50 travellers. The planner recommends the smallest suitable active vehicle. Customers can select a larger suitable vehicle.

Every vehicle has an editable daily rate with driver and an editable with-driver kilometre rate. The kilometre rate starts at €0.30/km. Cars with a maximum capacity of 3 can additionally allow self-drive with a separate editable daily rate. Larger vehicles are always with driver. Vehicle days are total nights plus one calendar day. Google Maps supplies the driving distance; it can also be entered manually when a map route is unavailable.

The live route print and the saved itinerary print both list every selected service beneath the correct destination. Accommodation includes room count, meal plan and room/night rate; sights, activities and shops include their per-person price or “Included”.

When creating a tour item, use **+ Add new location** beside the destination field. After saving the location, the tour-item form opens again with the new location already selected. Latitude and longitude are required so the location can immediately be used by the Google route map.

## Google Maps route setup

The website works without a Maps key, but the interactive road route requires a Google Maps Platform project with billing enabled.

1. In Google Cloud, enable **Maps JavaScript API** and **Routes API**.
2. Create a browser API key.
3. Restrict the key to those two APIs.
4. Add website referrer restrictions, for example `http://localhost/*` for local testing and `https://your-domain.example/*` for production.
5. Sign in to the website admin and open **Settings**.
6. Paste the key. `DEMO_MAP_ID` can be used for testing; a Google Cloud Map ID can be entered for production.

The included destinations already have map coordinates. Coordinates for new places can be entered on the destination edit page. Existing installations are upgraded automatically on the next request; the database user must have permission to alter tables.

The destination editor also includes a clickable and draggable Google map after the API key is configured. Customers can print the current route from the planner and, after submitting, open a professional itinerary sheet. Administrators can open the same route and print/PDF view from every enquiry.
