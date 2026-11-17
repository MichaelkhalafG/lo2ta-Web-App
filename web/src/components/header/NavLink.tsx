"use client";

import type { ReactNode } from "react";
import { Link, usePathname } from "@/i18n/navigation";

/**
 * A header link. The active page gets a 2px text-1 rule along the header's bottom edge (board 04).
 */
export function NavLink({ href, children }: { href: string; children: ReactNode }) {
  const pathname = usePathname();
  const active = pathname === href || pathname.startsWith(`${href}/`);

  return (
    <Link
      href={href}
      aria-current={active ? "page" : undefined}
      className={`relative flex items-center px-3.5 transition-colors duration-150 hover:text-text-1 ${active ? "text-text-1" : ""}`}
    >
      {children}
      {active ? <span aria-hidden="true" className="absolute start-3.5 end-3.5 bottom-0 h-0.5 bg-text-1" /> : null}
    </Link>
  );
}
