/** A forward arrow that points to the inline end: ← in Arabic, → in English. */
export function Arrow({ className = "" }: { className?: string }) {
  return (
    <span aria-hidden="true" className={`inline-block rtl:-scale-x-100 ${className}`}>
      →
    </span>
  );
}
