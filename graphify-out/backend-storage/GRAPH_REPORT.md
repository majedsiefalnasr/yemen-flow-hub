# Graph Report - backend/storage  (2026-05-18)

## Corpus Check
- Corpus is ~3,226 words - fits in a single context window. You may not need a graph.

## Summary
- 131 nodes · 351 edges · 20 communities (18 shown, 2 thin omitted)
- Extraction: 100% EXTRACTED · 0% INFERRED · 0% AMBIGUOUS
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 18|Community 18]]
- [[_COMMUNITY_Community 19|Community 19]]

## God Nodes (most connected - your core abstractions)
1. `paths` - 36 edges
2. `responses` - 24 edges
3. `tags` - 21 edges
4. `summary` - 21 edges
5. `operationId` - 21 edges
6. `responses` - 21 edges
7. `tags` - 17 edges
8. `summary` - 17 edges
9. `operationId` - 17 edges
10. `requestBody` - 15 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Communities (20 total, 2 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.00
Nodes (12): description, put, put, put, put, put, operationId, responses (+4 more)

### Community 1 - "Community 1"
Cohesion: 0.00
Nodes (11): get, get, get, get, get, summary, tags, /api/auth/me (+3 more)

### Community 2 - "Community 2"
Cohesion: 0.00
Nodes (11): delete, delete, delete, delete, delete, delete, operationId, responses (+3 more)

### Community 3 - "Community 3"
Cohesion: 0.00
Nodes (10): post, post, post, get, post, /api/banks, /api/notifications/read-all, /api/users (+2 more)

### Community 4 - "Community 4"
Cohesion: 0.00
Nodes (8): get, get, paths, /api/audit, /api/customs/{id}/download, /api/document-types/{id}, /api/documents/{id}, /api/voting/{id}

### Community 5 - "Community 5"
Cohesion: 0.00
Nodes (8): get, get, get, get, security, /api/banks/{id}, /api/requests/{id}, /api/users/{id}

### Community 6 - "Community 6"
Cohesion: 0.00
Nodes (7): description, get, get, responses, /api/merchants/{id}, /api/requests/{id}/history, 401

### Community 7 - "Community 7"
Cohesion: 0.00
Nodes (7): get, get, get, description, /api/dashboard/stats, /api/reports/voting, /api/reports/workflow

### Community 8 - "Community 8"
Cohesion: 0.00
Nodes (6): info, description, title, version, openapi, servers

### Community 9 - "Community 9"
Cohesion: 0.00
Nodes (7): description, description, post, /api/voting/{importRequest}/vote, responses, 201, 422

### Community 10 - "Community 10"
Cohesion: 0.00
Nodes (7): post, post, post, /api/customs/{importRequest}/generate, /api/requests/{importRequest}/documents, /api/workflow/{importRequest}/bank-approve, parameters

### Community 11 - "Community 11"
Cohesion: 0.00
Nodes (6): post, post, /api/workflow/{importRequest}/submit, /api/workflow/{importRequest}/swift-upload, requestBody, required

### Community 12 - "Community 12"
Cohesion: 0.00
Nodes (5): get, get, operationId, /api/customs/{id}, /api/documents/{id}/download

### Community 13 - "Community 13"
Cohesion: 0.00
Nodes (5): schema, application/json, multipart/form-data, schema, content

### Community 14 - "Community 14"
Cohesion: 0.00
Nodes (5): post, post, /api/auth/logout, /api/notifications/{notification}/read, tags

### Community 15 - "Community 15"
Cohesion: 0.00
Nodes (5): post, post, /api/auth/login, /api/voting/{importRequest}/director-decide, operationId

### Community 16 - "Community 16"
Cohesion: 0.00
Nodes (3): get, post, /api/document-types

### Community 17 - "Community 17"
Cohesion: 0.00
Nodes (3): get, post, /api/merchants

## Knowledge Gaps
- **13 isolated node(s):** `openapi`, `title`, `description`, `version`, `servers` (+8 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **2 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.