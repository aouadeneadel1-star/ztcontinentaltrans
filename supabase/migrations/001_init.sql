-- ZT Continental Trans : structure complète (Supabase / PostgreSQL)

-- ---------- Profils (un par compte de connexion) ----------
create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  role text not null default 'driver' check (role in ('admin','manager','driver')),
  first_name text not null default '',
  last_name text not null default '',
  phone text,
  is_active boolean not null default false,
  must_change_password boolean not null default false,
  created_at timestamptz not null default now()
);

create function public.my_role() returns text
language sql stable security definer set search_path = public as $$
  select role from public.profiles where id = auth.uid() and is_active
$$;
create function public.is_staff() returns boolean
language sql stable security definer set search_path = public as $$
  select coalesce(public.my_role() in ('admin','manager'), false)
$$;
create function public.is_admin() returns boolean
language sql stable security definer set search_path = public as $$
  select coalesce(public.my_role() = 'admin', false)
$$;

-- Toute inscription externe crée un profil INACTIF : seul un administrateur peut activer un compte.
create function public.handle_new_user() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  insert into public.profiles (id, first_name, last_name, is_active)
  values (new.id, coalesce(new.raw_user_meta_data->>'first_name',''), coalesce(new.raw_user_meta_data->>'last_name',''), false)
  on conflict (id) do nothing;
  return new;
end $$;
create trigger on_auth_user_created after insert on auth.users
  for each row execute function public.handle_new_user();

create function public.complete_password_change() returns void
language sql security definer set search_path = public as $$
  update public.profiles set must_change_password = false where id = auth.uid()
$$;

-- ---------- Camions ----------
create table public.trucks (
  id bigint generated always as identity primary key,
  plate text not null unique,
  brand text not null default '',
  model text not null default '',
  year int,
  vehicle_type text,
  volume_m3 numeric(6,1),
  mileage int not null default 0,
  status text not null default 'available' check (status in ('available','in_service','maintenance','immobilized')),
  last_service_date date,
  next_service_date date,
  insurance_info text,
  registration_number text,
  photo_path text,
  ownership text not null default 'owned' check (ownership in ('owned','rented')),
  rental_company text,
  rental_contract_no text,
  rental_start_date date,
  rental_end_date date,
  rental_monthly_cost numeric(10,2),
  created_at timestamptz not null default now()
);

create table public.truck_assignments (
  id bigint generated always as identity primary key,
  truck_id bigint not null references public.trucks(id) on delete cascade,
  driver_id uuid not null references public.profiles(id) on delete cascade,
  start_date timestamptz not null default now(),
  end_date timestamptz
);
create unique index one_active_assignment_per_truck on public.truck_assignments(truck_id) where end_date is null;
create unique index one_active_assignment_per_driver on public.truck_assignments(driver_id) where end_date is null;

create function public.my_truck_ids() returns setof bigint
language sql stable security definer set search_path = public as $$
  select truck_id from public.truck_assignments where driver_id = auth.uid() and end_date is null
$$;

create table public.rental_renewals (
  id bigint generated always as identity primary key,
  truck_id bigint not null references public.trucks(id) on delete cascade,
  previous_end_date date,
  new_end_date date not null,
  monthly_cost numeric(10,2),
  note text,
  created_by uuid references public.profiles(id) on delete set null,
  created_at timestamptz not null default now()
);

create table public.maintenance_records (
  id bigint generated always as identity primary key,
  truck_id bigint not null references public.trucks(id) on delete cascade,
  service_date date not null,
  service_type text not null,
  description text,
  mileage int,
  cost numeric(10,2),
  garage text,
  next_service_date date,
  created_by uuid references public.profiles(id) on delete set null,
  created_at timestamptz not null default now()
);

create table public.truck_documents (
  id bigint generated always as identity primary key,
  truck_id bigint not null references public.trucks(id) on delete cascade,
  category text not null check (category in ('registration','insurance','inspection','transport_auth','rental_contract','maintenance','other')),
  title text not null,
  file_path text not null,
  original_name text,
  expires_on date,
  uploaded_by uuid references public.profiles(id) on delete set null,
  created_at timestamptz not null default now()
);

-- ---------- Contrôles photo ----------
create table public.daily_checks (
  id bigint generated always as identity primary key,
  truck_id bigint not null references public.trucks(id) on delete cascade,
  driver_id uuid not null references public.profiles(id) on delete cascade,
  check_type text not null check (check_type in ('morning','evening')),
  check_date date not null default current_date,
  mileage int,
  comment text,
  created_at timestamptz not null default now(),
  unique (truck_id, check_type, check_date)
);
create table public.truck_photos (
  id bigint generated always as identity primary key,
  check_id bigint not null references public.daily_checks(id) on delete cascade,
  file_path text not null,
  created_at timestamptz not null default now()
);

-- ---------- Signalements ----------
create table public.incidents (
  id bigint generated always as identity primary key,
  truck_id bigint not null references public.trucks(id) on delete cascade,
  driver_id uuid not null references public.profiles(id) on delete cascade,
  category text not null check (category in ('engine','tires','brakes','lights','bodywork','electrical','documents','other')),
  description text not null,
  priority text not null default 'medium' check (priority in ('low','medium','high','critical')),
  status text not null default 'new' check (status in ('new','in_progress','pending','resolved')),
  safety_risk boolean not null default false,
  created_at timestamptz not null default now(),
  resolved_at timestamptz
);
create table public.incident_photos (
  id bigint generated always as identity primary key,
  incident_id bigint not null references public.incidents(id) on delete cascade,
  file_path text not null,
  created_at timestamptz not null default now()
);
create table public.incident_comments (
  id bigint generated always as identity primary key,
  incident_id bigint not null references public.incidents(id) on delete cascade,
  author_id uuid not null references public.profiles(id) on delete cascade,
  body text not null,
  created_at timestamptz not null default now()
);

-- ---------- Rippeurs, tournées, messages WhatsApp ----------
create table public.helpers (
  id bigint generated always as identity primary key,
  first_name text not null,
  last_name text not null,
  whatsapp text not null check (whatsapp ~ '^[1-9][0-9]{8,14}$'),
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);
create table public.dispatches (
  id bigint generated always as identity primary key,
  work_date date not null,
  truck_id bigint not null references public.trucks(id) on delete cascade,
  driver_id uuid not null references public.profiles(id) on delete cascade,
  helper1_id bigint references public.helpers(id) on delete set null,
  helper2_id bigint references public.helpers(id) on delete set null,
  note text check (char_length(note) <= 500),
  status text not null default 'draft' check (status in ('draft','validated','sent','partial','failed')),
  created_by uuid references public.profiles(id) on delete set null,
  validated_at timestamptz,
  created_at timestamptz not null default now()
);
create index on public.dispatches(work_date);
create table public.dispatch_tours (
  id bigint generated always as identity primary key,
  dispatch_id bigint not null references public.dispatches(id) on delete cascade,
  position int not null default 1,
  kind text not null default 'tour' check (kind in ('tour','run')),
  client text not null,
  city text not null,
  file_path text,
  original_name text,
  token uuid not null default gen_random_uuid() unique
);
create table public.dispatch_messages (
  id bigint generated always as identity primary key,
  dispatch_id bigint not null references public.dispatches(id) on delete cascade,
  recipient_type text not null check (recipient_type in ('driver','helper')),
  recipient_name text not null,
  phone text,
  body text not null,
  params jsonb,
  status text not null default 'pending' check (status in ('pending','sent','failed')),
  provider_id text,
  error_message text,
  attempts int not null default 0,
  sent_at timestamptz,
  created_at timestamptz not null default now()
);

-- ---------- Sécurité par ligne (RLS) ----------
alter table public.profiles enable row level security;
alter table public.trucks enable row level security;
alter table public.truck_assignments enable row level security;
alter table public.rental_renewals enable row level security;
alter table public.maintenance_records enable row level security;
alter table public.truck_documents enable row level security;
alter table public.daily_checks enable row level security;
alter table public.truck_photos enable row level security;
alter table public.incidents enable row level security;
alter table public.incident_photos enable row level security;
alter table public.incident_comments enable row level security;
alter table public.helpers enable row level security;
alter table public.dispatches enable row level security;
alter table public.dispatch_tours enable row level security;
alter table public.dispatch_messages enable row level security;

create policy profiles_read on public.profiles for select to authenticated
  using (id = auth.uid() or public.is_staff());
create policy profiles_admin_write on public.profiles for update to authenticated
  using (public.is_admin()) with check (public.is_admin());

create policy trucks_staff on public.trucks for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy trucks_driver_read on public.trucks for select to authenticated using (id in (select public.my_truck_ids()));

create policy assign_staff on public.truck_assignments for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy assign_driver_read on public.truck_assignments for select to authenticated using (driver_id = auth.uid());

create policy renewals_staff on public.rental_renewals for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy maint_staff on public.maintenance_records for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy docs_staff on public.truck_documents for all to authenticated using (public.is_staff()) with check (public.is_staff());

create policy checks_staff on public.daily_checks for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy checks_driver_read on public.daily_checks for select to authenticated using (driver_id = auth.uid());
create policy checks_driver_insert on public.daily_checks for insert to authenticated
  with check (driver_id = auth.uid() and truck_id in (select public.my_truck_ids()));
create policy cphotos_staff on public.truck_photos for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy cphotos_driver_read on public.truck_photos for select to authenticated
  using (check_id in (select id from public.daily_checks where driver_id = auth.uid()));
create policy cphotos_driver_insert on public.truck_photos for insert to authenticated
  with check (check_id in (select id from public.daily_checks where driver_id = auth.uid()));

create policy inc_staff on public.incidents for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy inc_driver_read on public.incidents for select to authenticated using (driver_id = auth.uid());
create policy inc_driver_insert on public.incidents for insert to authenticated
  with check (driver_id = auth.uid() and status = 'new' and truck_id in (select public.my_truck_ids()));
create policy iphotos_staff on public.incident_photos for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy iphotos_driver_read on public.incident_photos for select to authenticated
  using (incident_id in (select id from public.incidents where driver_id = auth.uid()));
create policy iphotos_driver_insert on public.incident_photos for insert to authenticated
  with check (incident_id in (select id from public.incidents where driver_id = auth.uid()));
create policy icomments_staff on public.incident_comments for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy icomments_driver_read on public.incident_comments for select to authenticated
  using (incident_id in (select id from public.incidents where driver_id = auth.uid()));
create policy icomments_driver_insert on public.incident_comments for insert to authenticated
  with check (author_id = auth.uid() and incident_id in (select id from public.incidents where driver_id = auth.uid()));

create policy helpers_staff on public.helpers for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy disp_staff on public.dispatches for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy disp_driver_read on public.dispatches for select to authenticated using (driver_id = auth.uid() and status <> 'draft');
create policy tours_staff on public.dispatch_tours for all to authenticated using (public.is_staff()) with check (public.is_staff());
create policy tours_driver_read on public.dispatch_tours for select to authenticated
  using (dispatch_id in (select id from public.dispatches where driver_id = auth.uid() and status <> 'draft'));
create policy dmsg_staff on public.dispatch_messages for all to authenticated using (public.is_staff()) with check (public.is_staff());

-- ---------- Stockage privé ----------
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types) values
  ('photos',    'photos',    false, 12582912, array['image/jpeg','image/png','image/webp']),
  ('documents', 'documents', false, 10485760, array['application/pdf','image/jpeg','image/png','image/webp']),
  ('sheets',    'sheets',    false, 10485760, array['application/pdf','image/jpeg','image/png','image/webp'])
on conflict (id) do nothing;

create policy storage_staff_all on storage.objects for all to authenticated
  using (bucket_id in ('photos','documents','sheets') and public.is_staff())
  with check (bucket_id in ('photos','documents','sheets') and public.is_staff());
-- Un conducteur dépose et lit seulement dans son propre dossier : photos/<son id>/...
create policy storage_driver_photos_insert on storage.objects for insert to authenticated
  with check (bucket_id = 'photos' and public.my_role() = 'driver' and (storage.foldername(name))[1] = auth.uid()::text);
create policy storage_driver_photos_read on storage.objects for select to authenticated
  using (bucket_id = 'photos' and (storage.foldername(name))[1] = auth.uid()::text);
-- Feuilles de tournée : le conducteur lit celles de ses plannings validés
create policy storage_driver_sheets_read on storage.objects for select to authenticated
  using (bucket_id = 'sheets' and name in (
    select t.file_path from public.dispatch_tours t join public.dispatches d on d.id = t.dispatch_id
    where d.driver_id = auth.uid() and d.status <> 'draft' and t.file_path is not null));

-- Les fonctions ne sont appelables que par un utilisateur connecté
revoke execute on function public.my_role(), public.is_staff(), public.is_admin(), public.my_truck_ids(), public.complete_password_change(), public.handle_new_user() from public, anon;
grant execute on function public.my_role(), public.is_staff(), public.is_admin(), public.my_truck_ids(), public.complete_password_change() to authenticated;
