# -*- coding: utf-8 -*-
"""Argentec project portfolio, transcribed from
"Argentec_Project_Portfolio_Oct-2026.pdf" (status as of October 2026).

Each building is (name, configuration, area_sqft or None, stage, status).
stage is one of: "completed" (OC received / work complete),
"construction" (any active RCC, finishing or foundation work),
"future" (planned, not yet started).
Where the source gives one area for several buildings, it is set on the
project as "area" and the buildings carry None.

Edit this file when statuses change, then run:
    python3 scripts/build_projects.py
"""

AS_OF = "October 2026"

# Headline figures. Where the company profile and the portfolio differ,
# the higher figure is used (client decision, Nov 2026).
HEADLINE = {
    "projects": "40+",      # company profile (portfolio lists 20)
    "area": "10.75",        # Mn sq ft, portfolio total incl. future development
}

REGIONS = {
    "western": "Western Suburbs",
    "eastern": "Eastern Suburbs",
    "city": "Mumbai City",
    "thane": "Thane",
}

PROJECTS = [
    {"slug": "vogue", "name": "Vogue", "area_name": "Santacruz", "region": "western",
     "address": "Santacruz (W), Mumbai",
     "buildings": [("Full project", "G + 14", 25000, "completed", "Completed")]},
    {"slug": "ananda-residency", "name": "Ananda Residency", "area_name": "Borivali", "region": "western",
     "address": "Shimpoli, Borivali (W), Mumbai",
     "buildings": [("Full project", "G + 23", 530000, "completed", "Completed")]},
    {"slug": "ariana-residency", "name": "Ariana Residency", "area_name": "Borivali", "region": "western",
     "address": "Devipada, Borivali (E), Mumbai",
     "buildings": [("Full project", "G + 23", 315000, "completed", "Completed")]},
    {"slug": "nivan", "name": "Nivan", "area_name": "Khar", "region": "western",
     "address": "S.V. Road, Khar (W), Mumbai",
     "buildings": [("Full project", "G + 18", 100000, "completed", "Completed")]},
    {"slug": "casa-palazo", "name": "Casa Palazo", "area_name": "Borivali", "region": "western",
     "address": "Carter Road No. 3, Borivali (E), Mumbai",
     "buildings": [("Full project", "G + 23", 100000, "completed", "Completed")]},
    {"slug": "hubtown-premiere", "name": "Hubtown Premiere", "area_name": "Versova", "region": "western",
     "address": "Hubtown Premiere Road, Versova, towards Kokilaben Hospital, Andheri (W), Mumbai – 400061",
     "area": 635698,
     "buildings": [
         ("Non Town Area", "2B + 4P", None, "construction", "Basement finishing in progress"),
         ("Malibu 1A", "21 upper floors", None, "completed", "OC received, handover completed"),
         ("Beverly 1B", "21 upper floors", None, "completed", "OC received, handover completed"),
         ("Beverly 1C", "21 upper floors", None, "construction", "RCC 24th slab in progress; finishing at 16th slab"),
     ]},
    {"slug": "hubtown-seasons", "name": "Hubtown Seasons", "area_name": "Chembur", "region": "eastern",
     "address": "1 R.C. Marg, Sector 4, Shanti Baug, near Jain Temple, Chembur (E), Mumbai – 400071",
     "buildings": [
         ("Danitree (D Wing)", "G + 14", 95569, "completed", "OC received, handover completed"),
         ("Ecuador Cloud (E Wing)", "G + 14", 150051, "completed", "OC received, handover completed"),
     ]},
    {"slug": "prime-vista", "name": "Prime Vista", "area_name": "Ghatkopar", "region": "eastern",
     "address": "Laxmi Nagar, Ghatkopar Link Road, near Pant Nagar Bus Stop, Ghatkopar (E), Mumbai – 400075",
     "buildings": [
         ("Composite Rehab", "G + 16", 78900, "completed", "Completed"),
         ("Composite Sale", "G + 16", 37052, "completed", "Completed"),
         ("Sale", "G + 16", 81500, "completed", "Completed"),
     ]},
    {"slug": "antalya", "name": "Antalya", "area_name": "Jogeshwari – Oshiwara", "region": "western",
     "address": "Ghaswala Compound, Relief Road, opp. Oshiwara Muslim Kabristan, Jogeshwari (W), Mumbai – 400102",
     "buildings": [
         ("Palantium – Rehab 1", "G + 8", 70252, "completed", "OC received"),
         ("Palantium – Rehab 2", "G + 8", 70252, "completed", "OC received"),
         ("SilverAvenue – Rehab 3A", "G + 15", 124827, "completed", "OC received"),
         ("SilverAvenue – Rehab 3B", "G + 17", 112678, "construction", "RCC Part 1 – 5th slab in progress; Part 2 – 3rd slab in progress"),
         ("SilverAvenue – Rehab 3C & D", "G + 16", 155008, "construction", "RCC completed, finishing in progress"),
         ("SilverAvenue – Sale 1", "G + P3 + 35", 462786, "completed", "OC received, handover in progress"),
         ("SilverAvenue – Sale 1 Parking Tower", "G + 19", 30000, "construction", "RCC completed, mechanical completed"),
         ("SilverAvenue – Sale 2", "G + 40", 592395, "construction", "20th slab in progress"),
         ("SilverAvenue – R Mall", "LG + G + upper floors", 65625, "construction", "RCC completed, finishing in progress"),
         ("SilverAvenue – Rehab 4C", "G + 11", 65206, "construction", "RCC completed, finishing in progress"),
         ("SilverAvenue – Rehab 7", "G + 7", 13910, "completed", "CFO received, OC received"),
     ]},
    {"slug": "el-signora", "name": "El Signora", "area_name": "Jogeshwari – Oshiwara", "region": "western",
     "address": "New Link Road, near Lotus Petrol Pump, behind Multani’s Furnishing, Oshiwara, Jogeshwari (W), Mumbai – 400102",
     "buildings": [
         ("Rehab", "G + 22", 52500, "completed", "Completed"),
         ("Sale", "G + 23", 103500, "completed", "Completed"),
     ]},
    {"slug": "zenith", "name": "Zenith", "area_name": "Thane", "region": "thane",
     "address": "Pushpanjali Residency, Phase 1, near Vedant Hospital, Ghodbunder Road, Thane (W) – 400607",
     "area": 243000,
     "buildings": [
         ("S3", "G + 30", None, "completed", "OC received"),
         ("S4", "G + 30", None, "completed", "OC received"),
     ]},
    {"slug": "artteza", "name": "Artteza", "area_name": "Santacruz", "region": "western",
     "address": "Daulat Desai Road, Santacruz (W), Mumbai",
     "area": 240000,
     "buildings": [
         ("Wings A & B", "2B + G + 9", None, "completed", "OC received, handover completed"),
         ("Wings C & D", "2B + G + 9", None, "construction", "RCC completed, finishing in progress"),
     ]},
    {"slug": "anantaara", "name": "Anantaara", "area_name": "Borivali", "region": "western",
     "address": "Shimpoli, Borivali (W), Mumbai",
     "buildings": [
         ("Rehab", "B + G + 15", 99519, "construction", "RCC – OHT & LMR in progress; finishing in progress"),
         ("Sale", "G + P7 + 2 Amenity + 1 Service + 32", 503136, "construction", "RCC Part 1 & 2 – 15th slab"),
     ]},
    {"slug": "mid-tower-71", "name": "Mid Tower 71", "area_name": "Chembur", "region": "eastern",
     "address": "Sindhi Colony, Chembur, Mumbai",
     "buildings": [
         ("Rehab 2", "G + 23", 174351, "construction", "RCC at terrace slab; finishing up to 16th floor"),
         ("Sale 1", "G + 3P + 23", 139016, "construction", "RCC at terrace slab; finishing in progress"),
         ("Sale 2", "G + 3P + 23", 132462, "construction", "RCC at 23rd slab; finishing in progress"),
         ("Sale 3", "G + 3P + 23", 130000, "construction", "RCC Part 1 & 2 – 7th slab completed; Part 3 – 1st slab in progress"),
     ]},
    {"slug": "shivdi", "name": "Shivdi", "area_name": "Sewri", "region": "city",
     "address": "Ramtekdi, Shivdi, Mumbai",
     "buildings": [
         ("Rehab 1", "G + 22", 157346, "construction", "RCC at 19th slab, finishing in progress"),
         ("Rehab 2", "G + 22", 156544, "construction", "RCC completed, finishing in progress"),
         ("Rehab 3", "G + 39", 467167, "construction", "RCC 2nd slab in progress"),
     ]},
    {"slug": "beaumonte", "name": "Beaumonte", "area_name": "Sion", "region": "city",
     "address": "Sion Circle, Sion, Mumbai",
     "buildings": [
         ("Sale", "UG + P + P5 + E-Deck + 35", 548526, "construction", "Plinth completed"),
         ("Rehab (i)", "B2 + G + 45", 617772, "construction", "Shore pile completed, rock anchoring in progress"),
         ("Rehab (ii)", "B2 + G + 45", 448787, "construction", "Shore pile in progress"),
     ]},
    {"slug": "green-acre", "name": "Green Acre", "area_name": "Pali Hill", "region": "western",
     "address": "Pali Hill, Bandra (W), Mumbai",
     "buildings": [("Sale", "B4 + G + 16", 148629, "construction", "Part 1 – plinth completed; Part 2 – raft in progress")]},
    {"slug": "super-star", "name": "Super Star", "area_name": "Juhu", "region": "western",
     "address": "Juhu, Mumbai",
     "buildings": [("Sale", "B3 + P3 + E-Deck + G + 23", 180505, "construction", "Excavation & rock anchoring in progress")]},
    {"slug": "shivdi-phase-2", "name": "Shivdi Phase 2", "area_name": "Sewri", "region": "city",
     "address": "Ramtekdi, Shivdi, Mumbai",
     "area": 1200000,
     "buildings": [
         ("Tower 4", "G + 23", None, "future", "Future development – Phase 2"),
         ("Tower 5", "G + 23", None, "future", "Future development – Phase 2"),
         ("Tower 6", "G + 40", None, "future", "Future development – Phase 2"),
     ]},
    {"slug": "worli-2", "name": "Worli 2", "area_name": "Worli", "region": "city",
     "address": "Yadav Nagar, Worli, Mumbai",
     "area": 1100000,
     "buildings": [
         ("Tower 1", "G + 40", None, "future", "Future development"),
         ("Tower 2", "G + 40", None, "future", "Future development"),
         ("Tower 3", "G + 40", None, "future", "Future development"),
     ]},
]

# Totals stated in the source document, used to verify the transcription.
SOURCE_TOTALS = {"projects": 20, "area": 10754470, "buildings": 51, "completed": 22,
                 "construction": 23, "future_area": 2300000}
