import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "export",
  // hPanel shared hosting caps the account's OS process count (LVE nproc).
  // Next's static-generation and TypeScript-checking workers default to
  // one process per CPU core reported by the host, which exceeds that cap
  // and hangs the build with silent fork() retries. Force a single worker.
  experimental: {
    cpus: 1,
    workerThreads: false,
  },
};

export default nextConfig;
