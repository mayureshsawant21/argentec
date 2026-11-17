/* Supabase connection for the careers backend and the admin panel.

   Fill these two values from Supabase: Project Settings -> API.
   - url:     the Project URL, e.g. https://abcdefghijkl.supabase.co
   - anonKey: the "anon public" key
   The anon key is meant to be public: what it can do is limited by the row
   level security rules in supabase/setup.sql (anyone can apply and read open
   roles; only admin accounts can read applications or edit roles).
   NEVER put the service_role key here.

   While url is empty the site falls back to the job list built into
   careers.html, and the admin panel shows its setup screen. */
window.ARGENTEC_BACKEND = {
  url: "",
  anonKey: ""
};
