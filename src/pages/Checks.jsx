import { useState } from 'react';
import { supabase } from '../lib/supabase';
import { Badge, Plate, PageHead, Msg, Photo, useAsync, q } from '../components/ui';
import { L, fdate, fdatetime, fullName, today } from '../lib/utils';

export default function Checks() {
  const [date, setDate] = useState(today());
  const { loading, data, error } = useAsync(() => q(supabase.from('daily_checks')
    .select('*, trucks(plate), profiles(first_name,last_name), truck_photos(id,file_path)').eq('check_date', date).order('created_at', { ascending: false })), [date]);
  return (
    <>
      <PageHead title="Photos et contrôles"><input type="date" value={date} onChange={(e) => setDate(e.target.value)} style={{ width: 'auto' }} /></PageHead>
      <Msg>{error}</Msg>
      {loading ? <p className="muted">Chargement…</p> : (data ?? []).length === 0 ? <div className="card muted">Aucun contrôle le {fdate(date)}.</div> :
        data.map((c) => (
          <section className="card" key={c.id}>
            <div className="page-head" style={{ marginBottom: 10 }}><h2 style={{ margin: 0 }}><Plate>{c.trucks.plate}</Plate> · {L.check_type[c.check_type]}</h2><span className="muted small">{fullName(c.profiles)} · {fdatetime(c.created_at)}{c.mileage ? ` · ${c.mileage} km` : ''}</span></div>
            {c.comment && <p>{c.comment}</p>}
            <div className="photos">{c.truck_photos.map((p) => <Photo key={p.id} path={p.file_path} />)}</div>
          </section>
        ))}
    </>
  );
}
