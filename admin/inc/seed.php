<?php
/* Content that is on the website when the admin panel is first installed:
   the open roles on careers.html and the blog posts. Generated once from
   scripts/company_data.py and content/blog; after install, the admin panel
   is the place to edit them. */

function seed_content(PDO $pdo): void {
    if (!(int)$pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO jobs (slug, title, department, location, employment_type, experience, summary, duties, requirements, status, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ([
        ['site-engineer-civil', 'Site Engineer, Civil', 'Projects & site execution', 'Mumbai', 'Full-time', '2 to 5 years', 'Run day-to-day civil works on a high-rise residential site, from RCC to finishing.', '["Supervise RCC, masonry and finishing works against drawings and schedule", "Coordinate subcontractors and daily labour deployment", "Maintain site records, measurements and progress reports"]', '["B.E. or Diploma in Civil Engineering", "Experience on high-rise residential projects", "Working knowledge of AutoCAD and MS Excel"]', 'open', 0],
        ['mep-engineer', 'MEP Engineer', 'Engineering & MEP', 'Mumbai', 'Full-time', '3 to 6 years', 'Coordinate electrical, plumbing and fire-fighting services with the structure and finishes.', '["Review MEP drawings and resolve clashes before work starts", "Supervise installation, testing and commissioning", "Coordinate with consultants, vendors and the civil team"]', '["B.E. in Electrical or Mechanical Engineering", "Experience on residential or commercial buildings", "Knowledge of relevant codes and testing procedures"]', 'open', 1],
        ['planning-engineer', 'Planning Engineer', 'Planning, QS & contracts', 'Head office, Khar West', 'Full-time', '4 to 8 years', 'Build and track project schedules, and flag risks to the delivery programme early.', '["Prepare baseline schedules in MS Project or Primavera", "Track progress against the plan and report variances", "Support resource and cash-flow planning"]', '["B.E. in Civil Engineering", "Hands-on MS Project or Primavera", "Experience across multiple concurrent projects"]', 'open', 2],
        ['quantity-surveyor', 'Quantity Surveyor', 'Planning, QS & contracts', 'Head office, Khar West', 'Full-time', '3 to 7 years', 'Prepare estimates, check measurements and certify contractor and vendor bills.', '["Prepare BOQs and cost estimates from drawings", "Certify subcontractor and vendor bills", "Track variations and reconcile materials"]', '["B.E. or Diploma in Civil Engineering", "Strong estimation and billing experience", "Advanced MS Excel"]', 'open', 3],
        ['safety-officer', 'Safety Officer', 'Safety & quality', 'Mumbai and Thane sites', 'Full-time', '2 to 5 years', 'Keep sites safe through inspections, inductions and toolbox talks.', '["Run safety inductions and daily toolbox talks", "Inspect scaffolding, lifting equipment and work at height", "Record incidents and follow up corrective actions"]', '["Recognised diploma in industrial or construction safety", "Experience on building construction sites", "Confident communicating in Hindi and Marathi"]', 'open', 4],
        ['procurement-executive', 'Procurement Executive', 'Procurement', 'Head office, Khar West', 'Full-time', '2 to 4 years', 'Source materials and services, compare quotes and follow orders through to delivery.', '["Float enquiries and prepare comparative statements", "Raise purchase orders and track deliveries to site", "Maintain the approved vendor list"]', '["Graduate, preferably in engineering or commerce", "Construction materials purchasing experience", "Good negotiation and follow-up skills"]', 'open', 5],
        ] as $j) {
            $st->execute($j);
        }
    }
    if (!(int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO posts (slug, title, category, excerpt, body, cover, cover_alt, author, status, published_at, noindex) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ([
        ['before-construction-starts', 'Five questions to settle before construction starts', 'Pre-construction', 'Most cost and schedule overruns trace back to decisions left open before the first excavator arrives. Here are five worth closing early.', '<p>Every building project has a moment when drawings turn into concrete. The decisions made before that moment are cheap to change. The ones made after it are not.</p>
<p>This is sample content for the blog layout. Replace it with a real article before the site goes live.</p>
<h2 id="1-is-the-scope-written-down-in-one-place">1. Is the scope written down in one place?</h2>
<p>Architects, consultants and the client often hold slightly different versions of the scope. A single scope document, agreed by all of them, removes most of the arguments that come later.</p>
<ul><li>What is included, and what is explicitly excluded</li><li>Which specification each package follows</li><li>Who approves a change, and how quickly</li></ul>
<h2 id="2-has-the-site-been-fully-investigated">2. Has the site been fully investigated?</h2>
<p>Soil reports, existing services and access routes all shape the foundation design and the logistics plan. Surprises found during excavation are the most expensive kind.</p>
<h2 id="3-is-the-budget-tied-to-quantities">3. Is the budget tied to quantities?</h2>
<p>A budget built from measured quantities can be checked as the design develops. A budget built from a rate per square foot cannot.</p>
<blockquote><p>A schedule is only as reliable as the decisions it assumes have already been made.</p></blockquote>
<h2 id="4-does-the-schedule-show-the-critical-path">4. Does the schedule show the critical path?</h2>
<p>The programme should make clear which activities hold up everything else, so that the team knows where a day lost really costs a day.</p>
<h2 id="5-who-coordinates-the-services">5. Who coordinates the services?</h2>
<p>Structural, mechanical, electrical and plumbing drawings need to be checked against each other before work starts. Clashes resolved on paper cost a fraction of clashes resolved on site.</p>
<h2 id="closing-the-loop">Closing the loop</h2>
<p>None of these questions is new. What matters is answering them before mobilisation rather than during it. If you are planning a project and want a second view on any of them, <a href="../contact.html?type=development">talk to our team</a>.</p>', 'assets/img/blog/before-construction-starts.svg', 'Line drawing of a building elevation with a tower crane over a site grid', 'Argentec Editorial', 'published', '2026-11-17 09:00:00', 1],
        ] as $p) {
            $st->execute($p);
        }
    }
    $st = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
    $st->execute(['careers_departments', json_encode(['Projects & site execution', 'Engineering & MEP', 'Planning, QS & contracts', 'Procurement', 'Safety & quality', 'Corporate functions'], JSON_UNESCAPED_UNICODE)]);
}
