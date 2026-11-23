"use client";

import { useCallback, useId, useRef, useState, type KeyboardEvent } from "react";
import { Link, usePathname } from "@/i18n/navigation";

export type MenuItem = { href: string; code: string; label: string; hint: string | null };

/**
 * A header dropdown (board 04): opens on hover and on click, closes on Esc and when focus or the pointer leaves;
 * arrow keys move between items. A disclosure button with a list of links, not an ARIA menu, because every item
 * is a plain navigation link.
 */
export function NavMenu({ label, items }: { label: string; items: MenuItem[] }) {
  const pathname = usePathname();
  // The panel remembers the page it was opened on, so a navigation closes it without an effect.
  const [openOn, setOpenOn] = useState<string | null>(null);
  const open = openOn === pathname;
  const setOpen = useCallback(
    (next: boolean | ((current: boolean) => boolean)) =>
      setOpenOn((current) => {
        const value = typeof next === "function" ? next(current === pathname) : next;
        return value ? pathname : null;
      }),
    [pathname],
  );
  const id = useId();
  const root = useRef<HTMLDivElement>(null);
  const button = useRef<HTMLButtonElement>(null);
  const links = useRef<(HTMLAnchorElement | null)[]>([]);
  const active = items.some((item) => pathname === item.href);

  const focusItem = useCallback((index: number) => {
    const count = links.current.length;
    links.current[((index % count) + count) % count]?.focus();
  }, []);

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const current = links.current.findIndex((link) => link === document.activeElement);
    if (event.key === "Escape" && open) {
      event.preventDefault();
      setOpen(false);
      button.current?.focus();
    } else if (event.key === "ArrowDown") {
      event.preventDefault();
      setOpen(true);
      requestAnimationFrame(() => focusItem(current + 1));
    } else if (event.key === "ArrowUp" && open) {
      event.preventDefault();
      focusItem(current - 1);
    } else if (event.key === "Home" && open) {
      event.preventDefault();
      focusItem(0);
    } else if (event.key === "End" && open) {
      event.preventDefault();
      focusItem(items.length - 1);
    }
  };

  return (
    <div
      ref={root}
      className="relative flex"
      onPointerEnter={(e) => e.pointerType === "mouse" && setOpen(true)}
      onPointerLeave={(e) => e.pointerType === "mouse" && setOpen(false)}
      onKeyDown={onKeyDown}
      onBlur={(e) => {
        if (!root.current?.contains(e.relatedTarget as Node | null)) {
          setOpen(false);
        }
      }}
    >
      <button
        ref={button}
        type="button"
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen((v) => !v)}
        className={`relative flex items-center gap-1.5 px-3.5 transition-colors duration-150 hover:text-text-1 ${open || active ? "text-text-1" : ""}`}
      >
        {label}
        {/* A drawn chevron, not a 10 px glyph: an icon, sized like the text it follows (audit D10). */}
        <svg aria-hidden="true" viewBox="0 0 10 6" className="h-1.5 w-2.5 fill-none stroke-text-3" strokeWidth="1.5">
          <path d="M1 1l4 4 4-4" />
        </svg>
        {active ? <span aria-hidden="true" className="absolute start-3.5 end-3.5 bottom-0 h-0.5 bg-text-1" /> : null}
      </button>
      <div
        id={id}
        hidden={!open}
        className="absolute start-0 top-full z-40 w-[22rem] border border-line bg-surface-1 py-2 shadow-[0_16px_40px_rgb(0_0_0/0.45)]"
      >
        <ul>
          {items.map((item, index) => (
            <li key={item.href}>
              <Link
                ref={(el) => {
                  links.current[index] = el;
                }}
                href={item.href}
                aria-current={pathname === item.href ? "page" : undefined}
                className="grid grid-cols-[2.5rem_1fr] gap-x-2 px-4 py-3 transition-colors duration-150 hover:bg-surface-2 focus-visible:bg-surface-2"
              >
                <span className="font-mono text-annotation text-text-3">{item.code}</span>
                <span className="flex flex-col gap-0.5">
                  <span className="text-text-1">{item.label}</span>
                  {item.hint ? <span className="text-small text-text-3">{item.hint}</span> : null}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
