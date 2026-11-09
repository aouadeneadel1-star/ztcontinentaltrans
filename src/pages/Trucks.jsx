import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { supabase } from '../lib/supabase';
import { Badge, Plate, PageHead, Table, Msg, useAsync, q } from '../components/ui';
import { L, fdate, fullName } from '../lib/utils';

export default function Trucks() {
  const [search, setSearch] = useState(''); const [status, setStatus] = useState(''); const [own, setOwn] = useState('');
  const { loading, data, error } = useAsync(async () => {
    const [trucks, assigns] = await Promise.all([
      q(supabase.from('trucks').select('*').order('plate')),
      q(supabase.from('truck_assignments').select('truck_id,profiles(first_name,last_name)').is('end_date', null)),
    ]);
    return trucks.map((t) => ({ ...t, driver: assigns.find((a) => a.truck_id === t.id)?.profiles }));
  }, []);
  const rows = (data ?? []).filter((t) => (!status || t.status === status) && (!own || t.ownership === own)
    && (!search || `${t.plate} ${t.brand} ${t.model}`.toLowerCase().includes(search.toLowerCase())));
  return (
    <>
      <PageHead title="Camions"><Link className="btn" to="/camions/nouveau">Ajouter un camion</Link></PageHead>
      <div className="card"><div className="grid3">
        <div className="field"><input placeholder="Rechercher (plaque, marque…)" value={search} onChange={(e) => setSearch(e.target.value)} /></div>
        <div className="field"><select value={status} onChange={(e) => setStatus(e.target.value)}><option value="">Tous les statuts</option>{Object.entries(L.truck_status).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></div>
        <div className="field"><select value={own} onChange={(e) => setOwn(e.target.value)}><option value="">Propriété et location</option>{Object.entries(L.ownership).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></div>
      </div></div>
      <Msg>{error}</Msg>
      {loading ? <p className="muted">Chargement…</p> : (
        <Table rows={rows} empty="Aucun camion." cols={[
          { h: 'Plaque', r: (t) => <Link to={`/camions/${t.id}`}><Plate>{t.plate}</Plate></Link> },
          { h: 'Véhicule', r: (t) => `${t.brand} ${t.model}` },
          { h: 'Conducteur', r: (t) => (t.driver ? fullName(t.driver) : <span className="muted">Non affecté</span>) },
          { h: 'Statut', r: (t) => <Badge kind="truck_status" value={t.status} /> },
          { h: 'Contrat', cls: 'hide-sm', r: (t) => (t.ownership === 'rented' ? `Location · fin ${fdate(t.rental_end_date)}` : 'Propriété') },
        ]} />
      )}
    </>
  );
}
