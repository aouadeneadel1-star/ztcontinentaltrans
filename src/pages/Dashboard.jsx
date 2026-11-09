import { Link } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { Badge, Plate, PageHead, Msg, useAsync, q } from '../components/ui';
import { L, today, addDays, daysUntil, fdate, fullName } from '../lib/utils';

export default function Dashboard() {
  const { loading, data, error } = useAsync(async () => {
    const t = today();
    const [trucks, incidents, docs, checks, assigns, dispatches] = await Promise.all([
      q(supabase.from('trucks').select('*')),
      q(supabase.from('incidents').select('id,truck_id,category,priority,status,created_at,trucks(plate)').neq('status', 'resolved').order('created_at', { ascending: false })),
      q(supabase.from('truck_documents').select('id,title,category,expires_on,trucks(plate)').not('expires_on', 'is', null).lte('expires_on', addDays(30)).order('expires_on')),
      q(supabase.from('daily_checks').select('truck_id,check_type').eq('check_date', t)),
      q(supabase.from('truck_assignments').select('truck_id,driver_id,profiles(first_name,last_name)').is('end_date', null)),
      q(supabase.from('dispatches').select('id,status').eq('work_date', t)),
    ]);
    return { trucks, incidents, docs, checks, assigns, dispatches };
  }, []);
  if (loading) return <p className="muted">Chargement…</p>;
  if (error) return <Msg>{error}</Msg>;
  const { trucks, incidents, docs, checks, assigns, dispatches } = data;

  const hour = new Date().getHours();
  const missing = [];
  for (const a of assigns) {
    const tr = trucks.find((x) => x.id === a.truck_id); if (!tr) continue;
    for (const [type, h] of [['morning', 10], ['evening', 19]])
      if (hour >= h && !checks.some((c) => c.truck_id === a.truck_id && c.check_type === type)) missing.push({ tr, type, who: fullName(a.profiles) });
  }
  const rentals = trucks.filter((x) => x.ownership === 'rented' && x.rental_end_date && daysUntil(x.rental_end_date) <= 60).sort((a, b) => a.rental_end_date.localeCompare(b.rental_end_date));
  const service = trucks.filter((x) => x.next_service_date && daysUntil(x.next_service_date) <= 15).sort((a, b) => a.next_service_date.localeCompare(b.next_service_date));
  const count = (s) => trucks.filter((x) => x.status === s).length;

  return (
    <>
      <PageHead title="Tableau de bord"><Link className="btn" to="/tournees/nouveau">Nouveau planning</Link></PageHead>
      <div className="figures">
        <Link className="figure" to="/camions"><b>{trucks.length}</b><span>Camions</span></Link>
        <Link className="figure" to="/camions"><b>{count('in_service')}</b><span>En circulation</span></Link>
        <Link className="figure" to="/camions"><b>{count('maintenance') + count('immobilized')}</b><span>Maintenance / immobilisés</span></Link>
        <Link className={`figure ${incidents.length ? 'alert' : ''}`} to="/signalements"><b>{incidents.length}</b><span>Signalements ouverts</span></Link>
        <Link className="figure" to="/tournees"><b>{dispatches.length}</b><span>Plannings aujourd'hui</span></Link>
      </div>

      <div className="grid2">
        <section className="card"><h2>Contrôles manquants</h2>
          <ul className="rows">{missing.map((m, i) => <li key={i}><span className="l"><Plate>{m.tr.plate}</Plate> {L.check_type[m.type]} · <span className="muted">{m.who}</span></span><span className="badge red">Manquant</span></li>)}
            {!missing.length && <li className="muted">Aucun contrôle en retard.</li>}</ul></section>

        <section className="card"><h2>Signalements ouverts</h2>
          <ul className="rows">{incidents.slice(0, 6).map((i) => <li key={i.id}><Link className="l" to={`/signalements/${i.id}`}><Plate>{i.trucks.plate}</Plate> {L.incident_category[i.category]}</Link><Badge kind="priority" value={i.priority} /></li>)}
            {!incidents.length && <li className="muted">Aucun signalement ouvert.</li>}</ul></section>

        <section className="card"><h2>Fins de location</h2>
          <ul className="rows">{rentals.map((x) => <li key={x.id}><Link className="l" to={`/camions/${x.id}`}><Plate>{x.plate}</Plate> {x.rental_company}</Link><span className={`badge ${daysUntil(x.rental_end_date) <= 15 ? 'red' : 'orange'}`}>{daysUntil(x.rental_end_date) < 0 ? 'Terminée' : `dans ${daysUntil(x.rental_end_date)} j`} · {fdate(x.rental_end_date)}</span></li>)}
            {!rentals.length && <li className="muted">Aucune location n'arrive à échéance.</li>}</ul></section>

        <section className="card"><h2>Documents à renouveler</h2>
          <ul className="rows">{docs.map((d) => <li key={d.id}><span className="l"><Plate>{d.trucks.plate}</Plate> {d.title}</span><span className={`badge ${daysUntil(d.expires_on) <= 7 ? 'red' : 'orange'}`}>{fdate(d.expires_on)}</span></li>)}
            {!docs.length && <li className="muted">Aucun document n'expire bientôt.</li>}</ul></section>

        <section className="card"><h2>Entretiens à prévoir</h2>
          <ul className="rows">{service.map((x) => <li key={x.id}><Link className="l" to={`/camions/${x.id}`}><Plate>{x.plate}</Plate> {x.brand} {x.model}</Link><span className={`badge ${daysUntil(x.next_service_date) < 0 ? 'red' : 'orange'}`}>{fdate(x.next_service_date)}</span></li>)}
            {!service.length && <li className="muted">Aucun entretien à prévoir.</li>}</ul></section>
      </div>
    </>
  );
}
