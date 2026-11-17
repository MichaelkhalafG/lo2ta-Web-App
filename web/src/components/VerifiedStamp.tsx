/**
 * The round verified stamp (board 03, "Verified stamp"): blueprint cyan, never gold, so "checked" is never confused
 * with "cheap". It does not say who verified or when.
 */
export function VerifiedStamp({ label, className = "" }: { label: string; className?: string }) {
  return (
    <span
      className={`relative grid size-28 -rotate-12 place-items-center rounded-full border-2 border-verified bg-bg-0 text-verified ${className}`}
      role="img"
      aria-label={label}
    >
      <span aria-hidden="true" className="absolute inset-1.5 rounded-full border border-verified" />
      <span aria-hidden="true" className="flex flex-col items-center gap-0.5">
        <span className="text-[1.1875rem] font-bold">{label}</span>
        <span className="font-mono text-[0.625rem] font-medium tracking-[0.14em]" lang="en">
          LOKTA
        </span>
      </span>
    </span>
  );
}
