# Graph Report - backend/database  (2026-05-18)

## Corpus Check
- Corpus is ~15,623 words - fits in a single context window. You may not need a graph.

## Summary
- 147 nodes · 120 edges · 44 communities (30 shown, 14 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 32|Community 32]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 36|Community 36]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 39|Community 39]]
- [[_COMMUNITY_Community 40|Community 40]]
- [[_COMMUNITY_Community 41|Community 41]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 43|Community 43]]

## God Nodes (most connected - your core abstractions)
1. `RequestScenarioBuilder` - 13 edges
2. `CustomsDeclarationSeeder` - 2 edges
3. `RequestDocumentSeeder` - 2 edges
4. `AuditLogSeeder` - 2 edges
5. `MerchantSeeder` - 2 edges
6. `UserSeeder` - 2 edges
7. `PermissionSeeder` - 2 edges
8. `RequestVoteSeeder` - 2 edges
9. `NotificationSeeder` - 2 edges
10. `BankSeeder` - 2 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Communities (44 total, 14 thin omitted)

### Community 1 - "Community 1"
Cohesion: 0.00
Nodes (6): `audit_logs`, `banks`, `cache`, `cache_locks`, `customs_declarations`, `import_requests`

## Knowledge Gaps
- **6 isolated node(s):** ``audit_logs``, ``banks``, ``cache``, ``cache_locks``, ``customs_declarations`` (+1 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **14 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.