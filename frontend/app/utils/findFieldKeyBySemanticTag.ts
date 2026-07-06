import type { FieldSemanticTag, ResolvedFieldGroup } from '@/types/models'

/**
 * Locate the field `key` tagged with the given WP-4 semantic tag across a set
 * of resolved field groups. Returns `null` when no field carries that tag —
 * callers must not fall back to a hardcoded field key.
 */
export function findFieldKeyBySemanticTag(
  fieldGroups: ResolvedFieldGroup[],
  tag: FieldSemanticTag,
): string | null {
  for (const group of fieldGroups) {
    const match = group.fields.find((field) => field.semantic_tag === tag)
    if (match) return match.key
  }
  return null
}

/** True when the given field group contains a field tagged with `tag`. */
export function groupHasSemanticTag(group: ResolvedFieldGroup, tag: FieldSemanticTag): boolean {
  return group.fields.some((field) => field.semantic_tag === tag)
}
