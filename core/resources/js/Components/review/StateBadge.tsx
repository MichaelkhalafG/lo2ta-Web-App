import { Badge } from '@/Components/ui/badge';

/**
 * One meaning per tone across every operations screen (PLAN 2.7): waiting for the team, in progress, done, stopped,
 * or plain information. A screen picks the tone from the record's state; the words come from the screen.
 */
export type StateTone = 'waiting' | 'progress' | 'done' | 'stopped' | 'info';

const VARIANT = {
    waiting: 'warning',
    progress: 'default',
    done: 'success',
    stopped: 'destructive',
    info: 'neutral',
} as const;

export function StateBadge({ tone, children }: { tone: StateTone; children: React.ReactNode }) {
    return <Badge variant={VARIANT[tone]}>{children}</Badge>;
}
