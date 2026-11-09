import { useEffect, useState } from 'react';
import { L, TONE, signedUrl } from '../lib/utils';

export const Badge = ({ kind, value, text }) => <span className={`badge ${TONE[value] ?? 'neutral'}`}>{text ?? L[kind]?.[value] ?? value}</span>;
export const Plate = ({ children }) => <span className="plate">{children}</span>;
export const Msg = ({ type = 'error', children }) => (children ? <div className={`alert-box ${type}`}>{children}</div> : null);

export function Field({ label, hint, children, className = '' }) {
  return <div className={`field ${className}`}>{label && <label>{label}</label>}{children}{hint && <span className="hint">{hint}</span>}</div>;
}
export const PageHead = ({ title, children }) => <div className="page-head"><h1>{title}</h1><div className="actions">{children}</div></div>;

// Tableau qui devient une liste de cartes sur téléphone
export function Table({ cols, rows, empty = 'Aucun élément.' }) {
  return (
    <div className="card flush">
      <table className="resp">
        <thead><tr>{cols.map((c) => <th key={c.h} className={c.cls}>{c.h}</th>)}</tr></thead>
        <tbody>
          {rows.length === 0 && <tr><td className="empty" colSpan={cols.length}>{empty}</td></tr>}
          {rows.map((r, i) => <tr key={r.id ?? i}>{cols.map((c) => <td key={c.h} data-l={c.h} className={c.cls}>{c.r(r)}</td>)}</tr>)}
        </tbody>
      </table>
    </div>
  );
}

// Image d'un bucket privé (lien signé temporaire)
export function Photo({ bucket = 'photos', path, alt = '' }) {
  const [url, setUrl] = useState(null);
  useEffect(() => { let ok = true; signedUrl(bucket, path).then((u) => ok && setUrl(u)); return () => { ok = false; }; }, [bucket, path]);
  return url ? <a href={url} target="_blank" rel="noreferrer"><img src={url} alt={alt} loading="lazy" /></a> : <div style={{ aspectRatio: '1', background: '#eef1f7', borderRadius: 8 }} />;
}

export async function openFile(bucket, path, download) {
  const u = await signedUrl(bucket, path, download);
  if (u) window.open(u, '_blank', 'noopener');
}

export function useAsync(fn, deps) {
  const [state, set] = useState({ loading: true, data: null, error: null });
  const [n, setN] = useState(0);
  useEffect(() => {
    let ok = true;
    set((s) => ({ ...s, loading: true }));
    Promise.resolve().then(fn).then((data) => ok && set({ loading: false, data, error: null }))
      .catch((error) => ok && set({ loading: false, data: null, error: error.message ?? String(error) }));
    return () => { ok = false; };
    // eslint-disable-next-line
  }, [...deps, n]);
  return { ...state, reload: () => setN((x) => x + 1) };
}

export const q = async (promise) => { const { data, error } = await promise; if (error) throw new Error(error.message); return data; };
