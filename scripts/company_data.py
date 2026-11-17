"""Content for the Vendors page and the Careers page.

Edit this file, then run:

    python3 scripts/build_company.py

OPEN ROLES
----------
Each role is a dict. `department` must be one of the keys in DEPARTMENTS.
Set SAMPLE_ROLES = False once the list holds Argentec's real openings; while
it is True the page labels the list as sample openings so nobody applies to
a role that does not exist. An empty ROLES list shows a "no openings right
now" message and keeps the general application form.
"""

# ---------------------------------------------------------------------------
# Vendors and subcontractors
# ---------------------------------------------------------------------------
VENDOR_STATS = [("550+", "Suppliers"), ("370+", "Subcontractors"), ("18", "Locations"), ("40+", "Projects")]

MATERIALS = [
    ("Structure", ["Ready-mix concrete and cement", "Reinforcement steel", "Blocks and masonry", "Formwork and scaffolding"]),
    ("Envelope", ["Waterproofing and construction chemicals", "Aluminium windows and glazing", "Facade systems", "Doors and hardware"]),
    ("Services", ["Electrical cables, wiring and switchgear", "Plumbing pipes, fittings and sanitaryware", "Fire-fighting equipment", "Lifts and HVAC equipment"]),
    ("Finishes", ["Tiles and natural stone", "Paints and coatings", "Gypsum and false ceilings", "Kitchen and bathroom fittings"]),
    ("Site", ["Tower cranes, hoists and equipment hire", "Safety equipment and PPE", "Site consumables", "Temporary works and site offices"]),
]

TRADES = [
    "RCC and civil works", "Shuttering and formwork", "Masonry and plaster", "Waterproofing",
    "Electrical installation", "Plumbing and drainage", "Fire-fighting systems", "HVAC",
    "Facade and glazing", "Flooring and tiling", "Painting and finishes", "External development and landscaping",
]

VENDOR_STEPS = [
    ("Register", "Submit the form below with your company details, categories and the cities you serve."),
    ("Review", "Our procurement team checks your details and documents, and may ask for references."),
    ("Qualify", "For larger packages we may visit a past site or your facility, or ask for samples."),
    ("Empanel", "Approved vendors join our list and receive enquiries for matching packages."),
]

VENDOR_DOCS = ["GST registration", "PAN of the business", "Company or firm registration",
               "Product catalogue or rate card", "Two or three recent client references"]

CITIES = ["Mumbai", "Thane", "Navi Mumbai", "Pune", "Other"]

# ---------------------------------------------------------------------------
# Careers
# ---------------------------------------------------------------------------
CAREER_STATS = [("90+", "Direct employees"), ("3,950+", "People on site every day"), ("40+", "Projects"), ("2015", "Building since")]

WHY_ARGENTEC = [
    ("Real responsibility early", "Site teams are lean, so engineers own their part of a project and see it through from slab to handover."),
    ("Projects that shape the city", "Residential towers, redevelopment and rehab buildings across Mumbai and Thane, from foundations to finishing."),
    ("Learn the whole build", "Planning, engineering, MEP, procurement and execution sit under one roof, so you learn how the pieces fit."),
    ("Safety first, every day", "Clear site procedures, regular audits and the right equipment, so everyone goes home safe."),
]

DEPARTMENTS = {
    "projects": "Projects & site execution",
    "engineering": "Engineering & MEP",
    "planning": "Planning, QS & contracts",
    "procurement": "Procurement",
    "safety": "Safety & quality",
    "corporate": "Corporate functions",
}

SAMPLE_ROLES = True

ROLES = [
    {
        "slug": "site-engineer-civil",
        "title": "Site Engineer, Civil",
        "department": "projects",
        "location": "Mumbai",
        "type": "Full-time",
        "experience": "2 to 5 years",
        "summary": "Run day-to-day civil works on a high-rise residential site, from RCC to finishing.",
        "duties": ["Supervise RCC, masonry and finishing works against drawings and schedule",
                   "Coordinate subcontractors and daily labour deployment",
                   "Maintain site records, measurements and progress reports"],
        "needs": ["B.E. or Diploma in Civil Engineering", "Experience on high-rise residential projects", "Working knowledge of AutoCAD and MS Excel"],
    },
    {
        "slug": "mep-engineer",
        "title": "MEP Engineer",
        "department": "engineering",
        "location": "Mumbai",
        "type": "Full-time",
        "experience": "3 to 6 years",
        "summary": "Coordinate electrical, plumbing and fire-fighting services with the structure and finishes.",
        "duties": ["Review MEP drawings and resolve clashes before work starts",
                   "Supervise installation, testing and commissioning",
                   "Coordinate with consultants, vendors and the civil team"],
        "needs": ["B.E. in Electrical or Mechanical Engineering", "Experience on residential or commercial buildings", "Knowledge of relevant codes and testing procedures"],
    },
    {
        "slug": "planning-engineer",
        "title": "Planning Engineer",
        "department": "planning",
        "location": "Head office, Khar West",
        "type": "Full-time",
        "experience": "4 to 8 years",
        "summary": "Build and track project schedules, and flag risks to the delivery programme early.",
        "duties": ["Prepare baseline schedules in MS Project or Primavera",
                   "Track progress against the plan and report variances",
                   "Support resource and cash-flow planning"],
        "needs": ["B.E. in Civil Engineering", "Hands-on MS Project or Primavera", "Experience across multiple concurrent projects"],
    },
    {
        "slug": "quantity-surveyor",
        "title": "Quantity Surveyor",
        "department": "planning",
        "location": "Head office, Khar West",
        "type": "Full-time",
        "experience": "3 to 7 years",
        "summary": "Prepare estimates, check measurements and certify contractor and vendor bills.",
        "duties": ["Prepare BOQs and cost estimates from drawings",
                   "Certify subcontractor and vendor bills",
                   "Track variations and reconcile materials"],
        "needs": ["B.E. or Diploma in Civil Engineering", "Strong estimation and billing experience", "Advanced MS Excel"],
    },
    {
        "slug": "safety-officer",
        "title": "Safety Officer",
        "department": "safety",
        "location": "Mumbai and Thane sites",
        "type": "Full-time",
        "experience": "2 to 5 years",
        "summary": "Keep sites safe through inspections, inductions and toolbox talks.",
        "duties": ["Run safety inductions and daily toolbox talks",
                   "Inspect scaffolding, lifting equipment and work at height",
                   "Record incidents and follow up corrective actions"],
        "needs": ["Recognised diploma in industrial or construction safety", "Experience on building construction sites", "Confident communicating in Hindi and Marathi"],
    },
    {
        "slug": "procurement-executive",
        "title": "Procurement Executive",
        "department": "procurement",
        "location": "Head office, Khar West",
        "type": "Full-time",
        "experience": "2 to 4 years",
        "summary": "Source materials and services, compare quotes and follow orders through to delivery.",
        "duties": ["Float enquiries and prepare comparative statements",
                   "Raise purchase orders and track deliveries to site",
                   "Maintain the approved vendor list"],
        "needs": ["Graduate, preferably in engineering or commerce", "Construction materials purchasing experience", "Good negotiation and follow-up skills"],
    },
]
