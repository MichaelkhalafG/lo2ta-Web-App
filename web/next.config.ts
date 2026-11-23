import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";
import { securityHeaders } from "./src/lib/securityHeaders.ts";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  // Pages use ISR (handover 2.2). Cache Components stay off until decided separately.
  poweredByHeader: false,
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders(process.env.NODE_ENV === "production") }];
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default withNextIntl(nextConfig);
