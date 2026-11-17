import type { ReactNode } from "react";

/** The <html> element lives in app/[locale]/layout.tsx, which knows the language and direction. */
export default function RootLayout({ children }: { children: ReactNode }) {
  return children;
}
