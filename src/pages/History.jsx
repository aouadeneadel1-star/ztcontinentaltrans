import { useState } from 'react';
import { supabase } from '../lib/supabase';
import { Badge, Plate, PageHead, Field, Msg, Table, useAsync, q } from '../components/ui';
import { L, fdate, fdatetime, fullName, downloadCSV, addDays, today } from '../lib/utils';

export default function History() {
  const [from, setFrom] = useState(addDays(-30)); const [to, setTo] = useState(today()); const [truck, setTruck] = useState(''); const [tab, setTab] = useState('checks');
  const trucks = useAsync(() => q(supabase.from('trucks').select('id,plate').order('plate')), []);
  const { loading, data, error } = useAsync(async () => {
    let c = supabase.from('daily_checks').select('*, trucks(plate), profiles(first_name,last_name), truck_photos(id)').gte('check_date', from).lte('check_date', to).order('check_date', { ascending: false });
    let i = supabase.from('incidents').select('*, trucks(plate), profiles(first_name,last_name)').gte('created_at', from).lte('created_at', to + 'T23:59:59').order('created_at', { ascending: false });
    if (truck) { c = c.eq('truck_id', truck); i = i.eq('truck_id', truck); }
    const [checks, incidents] = await Promise.all([q(c), q(i)]);
    return { checks, incidents };
  }, [from, to, truck]);
  function csv() {
    if (tab === 'checks') downloadCSV(`controles-${from}-${to}.csv`, [['Date', 'Camion', 'Contrôle', 'Conducteur', 'Km', 'Photos', 'Remarque'], ...data.checks.map((c) => [fdate(c.check_date), c.trucks.plate, L.check_type[c.check_type], fullName(c.profiles), c.mileage ?? '', c.truck_photos.length, c.comment ?? ''])]);
    else downloadCSV(`signalements-${from}-${to}.csv`, [['Date', 'Camion', 'Catégorie', 'Priorité', 'Statut', 'Conducteur', 'Description'], ...data.incidents.map((x) => [fdatetime(x.created_at), x.trucks.plate, L.incident_category[x.category], L.priority[x.priority], L.incident_status[x.status], fullName(x.profiles), x.description])]);
  }
  return (
    <>
      <PageHead title="Historique et exports"><button className="btn light" onClick={csv} disabled={!data}>Exporter en CSV</button><button className="btn light" onClick={() => window.print()}>Imprimer / PDF</button></PageHead>
      <div className="card"><div className="grid3">
        <Field label="Du"><input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></Field>
        <Field label="Au"><input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
        <Field label="Camion"><select value={truck} onChange={(e) => setTruck(e.target.value)}><option value="">Tous</option>{(trucks.data ?? []).map((t) => <option key={t.id} value={t.id}>{t.plate}</option>)}</select></Field></div></div>
      <div className="tabs"><button className={tab === 'checks' ? 'on' : ''} onClick={() => setTab('checks')}>Contrôles</button><button className={tab === 'inc' ? 'on' : ''} onClick={() => setTab('inc')}>Signalements</button></div>
      <Msg>{error}</Msg>
      {loading ? <p className="muted">Chargement…</p> : tab === 'checks' ? (
        <Table rows={data.checks} cols={[{ h: 'Date', r: (c) => fdate(c.check_date) }, { h: 'Camion', r: (c) => <Plate>{c.trucks.plate}</Plate> }, { h: 'Contrôle', r: (c) => L.check_type[c.check_type] }, { h: 'Conducteur', r: (c) => fullName(c.profiles) }, { h: 'Km', r: (c) => c.mileage ?? '—' }, { h: 'Photos', r: (c) => c.truck_photos.length }]} />
      ) : (
        <Table rows={data.incidents} cols={[{ h: 'Date', r: (x) => fdatetime(x.created_at) }, { h: 'Camion', r: (x) => <Plate>{x.trucks.plate}</Plate> }, { h: 'Problème', r: (x) => L.incident_category[x.category] }, { h: 'Priorité', r: (x) => <Badge kind="priority" value={x.priority} /> }, { h: 'Statut', r: (x) => <Badge kind="incident_status" value={x.status} /> }]} />
      )}
    </>
  );
}
