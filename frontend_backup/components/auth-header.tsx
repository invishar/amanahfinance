export function AuthHeader({
  title,
  subtitle,
}: {
  title: string;
  subtitle: string;
}) {
  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        gap: "var(--space-2)",
        marginBottom: "var(--space-2)",
      }}
    >
      <div
        className="amana-brand-circle"
        style={{ width: 56, height: 56, fontSize: 22 }}
      >
        AF
      </div>
      <h1 style={{ fontSize: 26, margin: 0, textAlign: "center" }}>{title}</h1>
      <p
        className="text-muted"
        style={{ margin: 0, fontSize: 13, textAlign: "center" }}
      >
        {subtitle}
      </p>
    </div>
  );
}
