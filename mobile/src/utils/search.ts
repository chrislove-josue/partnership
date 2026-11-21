/** Retire les accents et met en minuscules pour une recherche tolérante. */
export function normalizeText(value: unknown): string {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

/**
 * Vrai si tous les mots de la recherche apparaissent dans au moins un champ.
 * Une recherche vide accepte tout.
 */
export function matchesQuery(query: string, values: unknown[]): boolean {
  const terms = normalizeText(query).split(/\s+/).filter(Boolean);
  if (terms.length === 0) {
    return true;
  }

  const searchable = values.map(normalizeText);
  return terms.every((term) => searchable.some((value) => value.includes(term)));
}

/** Filtre une liste à partir des champs significatifs de chaque élément. */
export function filterBy<T>(
  items: T[],
  query: string,
  fields: Array<keyof T | ((item: T) => unknown)>,
): T[] {
  return items.filter((item) =>
    matchesQuery(
      query,
      fields.map((field) =>
        typeof field === 'function' ? field(item) : item[field],
      ),
    ),
  );
}
