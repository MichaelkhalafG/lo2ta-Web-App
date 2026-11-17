import { cn } from '@/lib/utils';

/** The board's wordmark fallback (LK-ID-00): "لقطة", a gold hairline, "LOKTA" tracked in gold. */
export function Wordmark({ compact = false, className }: { compact?: boolean; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-2.5 whitespace-nowrap', className)} lang="ar">
            <span className={cn('leading-none font-extrabold', compact ? 'text-lg' : 'text-2xl')}>لقطة</span>
            {compact ? null : (
                <>
                    <span aria-hidden="true" className="h-5 w-px bg-gold" />
                    <span lang="en" className="text-[11px] leading-none font-semibold tracking-[0.24em] text-gold">
                        LOKTA
                    </span>
                </>
            )}
        </span>
    );
}
