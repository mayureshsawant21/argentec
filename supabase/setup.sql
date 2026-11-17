-- ===========================================================================
-- Argentec careers backend (Supabase)
--
-- Run once in the Supabase dashboard: SQL Editor -> New query -> paste -> Run.
-- It creates:
--   job_postings   roles shown on careers.html (managed in /admin/)
--   applications   careers form submissions (read in /admin/)
--   admins         which logged-in users may use the admin panel
--   resumes        private storage bucket for uploaded resumes
-- Safe to run again: everything uses "if not exists" / "or replace".
-- ===========================================================================

create extension if not exists pgcrypto;

-- ---------------------------------------------------------------- tables
create table if not exists public.admins (
  user_id uuid primary key references auth.users (id) on delete cascade,
  created_at timestamptz not null default now()
);

create table if not exists public.job_postings (
  id uuid primary key default gen_random_uuid(),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  title text not null check (char_length(title) between 2 and 120),
  department text not null,
  location text not null,
  employment_type text not null default 'Full-time',
  experience text,
  summary text,
  duties text[] not null default '{}',
  requirements text[] not null default '{}',
  status text not null default 'draft' check (status in ('open', 'draft', 'closed')),
  sort_order integer not null default 0
);

create table if not exists public.applications (
  id uuid primary key default gen_random_uuid(),
  created_at timestamptz not null default now(),
  reference text not null,
  job_id uuid references public.job_postings (id) on delete set null,
  job_title text not null default 'General application',
  name text not null check (char_length(name) <= 120),
  phone text not null check (char_length(phone) <= 20),
  email text not null check (char_length(email) <= 160),
  location text check (char_length(location) <= 120),
  experience text,
  current_company text check (char_length(current_company) <= 160),
  current_ctc numeric(6, 2) check (current_ctc >= 0),
  expected_ctc numeric(6, 2) check (expected_ctc >= 0),
  resume_path text,
  linkedin text check (char_length(linkedin) <= 300),
  message text check (char_length(message) <= 4000),
  consent boolean not null default false,
  status text not null default 'new' check (status in ('new', 'shortlisted', 'interview', 'offered', 'hired', 'rejected')),
  notes text
);
create index if not exists applications_created_idx on public.applications (created_at desc);

-- keep updated_at current on job edits
create or replace function public.touch_updated_at() returns trigger language plpgsql as $$
begin new.updated_at = now(); return new; end $$;
drop trigger if exists job_postings_touch on public.job_postings;
create trigger job_postings_touch before update on public.job_postings
  for each row execute function public.touch_updated_at();

-- is the current user an admin?
create or replace function public.is_admin() returns boolean
  language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.admins where user_id = auth.uid());
$$;
grant execute on function public.is_admin() to anon, authenticated;

-- ------------------------------------------------------- row level security
alter table public.admins enable row level security;
alter table public.job_postings enable row level security;
alter table public.applications enable row level security;

drop policy if exists "admins see admins" on public.admins;
create policy "admins see admins" on public.admins for select to authenticated using (public.is_admin());

-- Job postings: the public sees open roles; admins manage everything
drop policy if exists "public reads open jobs" on public.job_postings;
create policy "public reads open jobs" on public.job_postings for select to anon, authenticated
  using (status = 'open' or public.is_admin());
drop policy if exists "admins insert jobs" on public.job_postings;
create policy "admins insert jobs" on public.job_postings for insert to authenticated with check (public.is_admin());
drop policy if exists "admins update jobs" on public.job_postings;
create policy "admins update jobs" on public.job_postings for update to authenticated using (public.is_admin()) with check (public.is_admin());
drop policy if exists "admins delete jobs" on public.job_postings;
create policy "admins delete jobs" on public.job_postings for delete to authenticated using (public.is_admin());

-- Applications: anyone can submit (but not read); only admins read and update
drop policy if exists "anyone applies" on public.applications;
create policy "anyone applies" on public.applications for insert to anon, authenticated
  with check (consent = true and status = 'new' and notes is null);
drop policy if exists "admins read applications" on public.applications;
create policy "admins read applications" on public.applications for select to authenticated using (public.is_admin());
drop policy if exists "admins update applications" on public.applications;
create policy "admins update applications" on public.applications for update to authenticated using (public.is_admin()) with check (public.is_admin());
drop policy if exists "admins delete applications" on public.applications;
create policy "admins delete applications" on public.applications for delete to authenticated using (public.is_admin());

-- ------------------------------------------------------------ resume storage
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('resumes', 'resumes', false, 5242880, array[
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
])
on conflict (id) do update set public = false, file_size_limit = excluded.file_size_limit,
  allowed_mime_types = excluded.allowed_mime_types;

drop policy if exists "anyone uploads a resume" on storage.objects;
create policy "anyone uploads a resume" on storage.objects for insert to anon, authenticated
  with check (bucket_id = 'resumes');
drop policy if exists "admins read resumes" on storage.objects;
create policy "admins read resumes" on storage.objects for select to authenticated
  using (bucket_id = 'resumes' and public.is_admin());
drop policy if exists "admins delete resumes" on storage.objects;
create policy "admins delete resumes" on storage.objects for delete to authenticated
  using (bucket_id = 'resumes' and public.is_admin());

-- ------------------------------------------------------------- sample roles
-- The website's sample openings, added as DRAFTS so nothing is public until
-- an admin edits and opens them. Only inserted when the table is empty.
insert into public.job_postings (title, department, location, employment_type, experience, summary, duties, requirements, status, sort_order)
select * from (values
  ('Site Engineer, Civil', 'Projects & site execution', 'Mumbai', 'Full-time', '2 to 5 years', 'Run day-to-day civil works on a high-rise residential site, from RCC to finishing.', array['Supervise RCC, masonry and finishing works against drawings and schedule', 'Coordinate subcontractors and daily labour deployment', 'Maintain site records, measurements and progress reports']::text[], array['B.E. or Diploma in Civil Engineering', 'Experience on high-rise residential projects', 'Working knowledge of AutoCAD and MS Excel']::text[], 'draft', 0),
  ('MEP Engineer', 'Engineering & MEP', 'Mumbai', 'Full-time', '3 to 6 years', 'Coordinate electrical, plumbing and fire-fighting services with the structure and finishes.', array['Review MEP drawings and resolve clashes before work starts', 'Supervise installation, testing and commissioning', 'Coordinate with consultants, vendors and the civil team']::text[], array['B.E. in Electrical or Mechanical Engineering', 'Experience on residential or commercial buildings', 'Knowledge of relevant codes and testing procedures']::text[], 'draft', 1),
  ('Planning Engineer', 'Planning, QS & contracts', 'Head office, Khar West', 'Full-time', '4 to 8 years', 'Build and track project schedules, and flag risks to the delivery programme early.', array['Prepare baseline schedules in MS Project or Primavera', 'Track progress against the plan and report variances', 'Support resource and cash-flow planning']::text[], array['B.E. in Civil Engineering', 'Hands-on MS Project or Primavera', 'Experience across multiple concurrent projects']::text[], 'draft', 2),
  ('Quantity Surveyor', 'Planning, QS & contracts', 'Head office, Khar West', 'Full-time', '3 to 7 years', 'Prepare estimates, check measurements and certify contractor and vendor bills.', array['Prepare BOQs and cost estimates from drawings', 'Certify subcontractor and vendor bills', 'Track variations and reconcile materials']::text[], array['B.E. or Diploma in Civil Engineering', 'Strong estimation and billing experience', 'Advanced MS Excel']::text[], 'draft', 3),
  ('Safety Officer', 'Safety & quality', 'Mumbai and Thane sites', 'Full-time', '2 to 5 years', 'Keep sites safe through inspections, inductions and toolbox talks.', array['Run safety inductions and daily toolbox talks', 'Inspect scaffolding, lifting equipment and work at height', 'Record incidents and follow up corrective actions']::text[], array['Recognised diploma in industrial or construction safety', 'Experience on building construction sites', 'Confident communicating in Hindi and Marathi']::text[], 'draft', 4),
  ('Procurement Executive', 'Procurement', 'Head office, Khar West', 'Full-time', '2 to 4 years', 'Source materials and services, compare quotes and follow orders through to delivery.', array['Float enquiries and prepare comparative statements', 'Raise purchase orders and track deliveries to site', 'Maintain the approved vendor list']::text[], array['Graduate, preferably in engineering or commerce', 'Construction materials purchasing experience', 'Good negotiation and follow-up skills']::text[], 'draft', 5)
) as v
where not exists (select 1 from public.job_postings);

-- ------------------------------------------------------------- first admin
-- 1. Authentication -> Users -> Add user -> Create new user (email + password,
--    tick "Auto Confirm User").
-- 2. Then run this line with that email:
--
-- insert into public.admins (user_id) select id from auth.users where email = 'hr@argentec.com';
