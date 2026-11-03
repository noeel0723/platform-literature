import ReaderCard from './ReaderCard';
import EmptyState from './EmptyState';

export default function ReaderRows({ readers, routes }) {
    if (!readers.length) return <EmptyState title="No readers found." description="Try another name or username." />;

    return (
        <div>
            <div aria-hidden="true" className="hidden grid-cols-[minmax(0,1fr)_15rem_2.25rem] gap-x-5 border-b border-ink-950/15 bg-white/30 py-2 text-[10px] font-semibold uppercase tracking-wider text-ink-950/50 sm:grid">
                <span>Name</span><div className="grid grid-cols-3 text-center"><span>Done</span><span>Lists</span><span>Followers</span></div><span className="sr-only">Follow</span>
            </div>
            <ul className="divide-y divide-ink-950/10 border-b border-ink-950/15">
                {readers.map((reader) => <li key={reader.username}><ReaderCard reader={reader} routes={routes} variant="row" /></li>)}
            </ul>
        </div>
    );
}
