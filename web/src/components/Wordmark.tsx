/**
 * The wordmark fallback from the board (LK-ID-00): "لقطة", a gold hairline, "LOKTA" tracked in gold.
 * Used until the final logo file replaces it.
 */
export function Wordmark({ size = "md" }: { size?: "md" | "sm" }) {
  return (
    <span className="inline-flex items-center gap-2.5 whitespace-nowrap" lang="ar">
      <span className={size === "md" ? "text-[1.625rem] leading-none font-extrabold" : "text-[1.375rem] leading-none font-extrabold"}>
        لقطة
      </span>
      <span aria-hidden="true" className="h-5 w-px bg-gold" />
      <span lang="en" className="text-micro leading-none font-semibold tracking-wordmark text-gold">
        LOKTA
      </span>
    </span>
  );
}
