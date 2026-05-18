# Graph Report - docs  (2026-05-18)

## Corpus Check
- Corpus is ~21,239 words - fits in a single context window. You may not need a graph.

## Summary
- 21 nodes · 34 edges · 3 communities (2 shown, 1 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 2 edges (avg confidence: 0.5)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]

## God Nodes (most connected - your core abstractions)
1. `docs/03-database-and-models.md` - 6 edges
2. `docs/05-backend-guide.md` - 6 edges
3. `docs/07-task-breakdown.md` - 4 edges
4. `docs/08-prototype-gap-analysis.md` - 4 edges
5. `docs/ux/missing-ui-states.md` - 4 edges
6. `docs/01-workflow-and-business-rules.md` - 3 edges
7. `docs/02-system-architecture.md` - 3 edges
8. `docs/04-frontend-guide.md` - 3 edges
9. `docs/06-api-reference.md` - 3 edges
10. `docs/00-project-brief.md` - 2 edges

## Surprising Connections (you probably didn't know these)
- `docs/07-task-breakdown.md` --aligns frontend task phases with workflow-oriented UI delivery--> `docs/04-frontend-guide.md`  [INFERRED]
  docs/07-task-breakdown.md → docs/04-frontend-guide.md
- `docs/07-task-breakdown.md` --aligns backend task phases with service-oriented workflow implementation--> `docs/05-backend-guide.md`  [INFERRED]
  docs/07-task-breakdown.md → docs/05-backend-guide.md
- `docs/04-frontend-guide.md` --declares status enum source of truth in--> `docs/03-database-and-models.md`  [EXTRACTED]
  docs/04-frontend-guide.md → docs/03-database-and-models.md
- `docs/08-prototype-gap-analysis.md` --references as implementation spec for unresolved UI gaps--> `docs/ux/missing-ui-states.md`  [EXTRACTED]
  docs/08-prototype-gap-analysis.md → docs/ux/missing-ui-states.md

## Communities (3 total, 1 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.00
Nodes (5): docs/05-backend-guide.md, docs/03-database-and-models.md, docs/04-frontend-guide.md, docs/02-system-architecture.md, docs/07-task-breakdown.md

### Community 1 - "Community 1"
Cohesion: 0.00
Nodes (3): docs/06-api-reference.md, docs/00-project-brief.md, docs/01-workflow-and-business-rules.md

## Knowledge Gaps
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.