import type { ComponentProps } from "react";
import { Link } from "@/i18n/navigation";
import { Arrow } from "./Arrow";

type Props = {
  href: ComponentProps<typeof Link>["href"];
  children: string;
  /** gold: the one primary action of a screen. paper: the primary action inside the light seller band. */
  tone?: "gold" | "paper";
};

/**
 * The primary action (board 04, "Actions"): 52px high, 2px corners, the arrow in its own cell, which is the
 * button's "dimension end".
 */
export function ArrowButton({ href, children, tone = "gold" }: Props) {
  const colours =
    tone === "gold"
      ? "bg-gold text-on-gold hover:bg-gold-bright"
      : "bg-paper-ink text-paper hover:bg-surface-2";
  const divider = tone === "gold" ? "border-on-gold/30" : "border-paper/25";

  return (
    <Link
      href={href}
      className={`inline-flex h-control-lg items-stretch rounded-sm text-[1rem] font-semibold whitespace-nowrap transition-colors duration-150 ${colours}`}
    >
      <span className="flex items-center px-5.5">{children}</span>
      <span className={`flex items-center border-s px-4 ${divider}`}>
        <Arrow />
      </span>
    </Link>
  );
}
