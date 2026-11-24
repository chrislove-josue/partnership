import React, { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import { FilterCard } from '../components/FilterCard';
import { filterBy } from '../utils/search';
import type { Category, Church } from '../api/types';
import { useAuth } from '../context/AuthContext';
import { colors } from '../components/ui';
import {
  Badge,
  Card,
  EmptyState,
  ErrorView,
  formatMoney,
  LoadingView,
  Screen,
  SectionTitle,
  SelectField,
} from '../components/ui';

const PERIODS = [
  { label: 'Par mois', value: 'month' },
  { label: 'Par trimestre', value: 'quarter' },
  { label: 'Par année', value: 'year' },
];

const MONTHS = [
  { label: 'Janvier', value: '1' }, { label: 'Février', value: '2' }, { label: 'Mars', value: '3' },
  { label: 'Avril', value: '4' }, { label: 'Mai', value: '5' }, { label: 'Juin', value: '6' },
  { label: 'Juillet', value: '7' }, { label: 'Août', value: '8' }, { label: 'Septembre', value: '9' },
  { label: 'Octobre', value: '10' }, { label: 'Novembre', value: '11' }, { label: 'Décembre', value: '12' },
];

const QUARTERS = [
  { label: 'T1', value: '1' }, { label: 'T2', value: '2' }, { label: 'T3', value: '3' }, { label: 'T4', value: '4' },
];

const CATEGORY_GROUPS = [
  { label: 'Toutes les familles', value: '' },
  { label: 'Finances de l’église', value: 'church_finances' },
  { label: 'Partenariat du ministère', value: 'ministry_partnership' },
  { label: 'Partenariat local', value: 'local_partnership' },
];

interface TopEntry {
  id: number;
  name: string;
  church: string | null;
  origin_church?: string | null;
  cellule: string | null;
  amount: number;
  count: number;
  is_user: boolean;
}

export function TopPartnersScreen() {
  const now = new Date();
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [items, setItems] = useState<TopEntry[]>([]);
  const [range, setRange] = useState('');
  const [search, setSearch] = useState('');
  const [period, setPeriod] = useState('month');
  const [month, setMonth] = useState(String(now.getMonth() + 1));
  const [quarter, setQuarter] = useState(String(Math.floor(now.getMonth() / 3) + 1));
  const [year, setYear] = useState(String(now.getFullYear()));
  const [churches, setChurches] = useState<Church[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [churchId, setChurchId] = useState(user?.assigned_church_id ? String(user.assigned_church_id) : '');
  const [categoryGroup, setCategoryGroup] = useState('');
  const [categoryId, setCategoryId] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams({ period, year });
      if (period === 'month') params.set('month', month);
      if (period === 'quarter') params.set('quarter', quarter);
      // Toujours envoyer church_id : vide = toutes les églises accessibles.
      params.set('church_id', churchId);
      if (categoryGroup) params.set('category_group', categoryGroup);
      if (categoryId) params.set('category_id', categoryId);

      const [response, churchData, categoryData] = await Promise.all([
        api<{
          period: string;
          year: number;
          from: string;
          to: string;
          data: TopEntry[];
        }>(`/top-partners?${params.toString()}`),
        api<{ data: Church[] }>('/churches', ),
        api<{ data: Category[] }>('/categories', ),
      ]);
      setItems(response.data);
      setRange(`${response.from} → ${response.to}`);
      setChurches(churchData.data);
      setCategories(categoryData.data);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Impossible de charger les top partenaires.'));
    } finally {
      setLoading(false);
    }
  }, [period, month, quarter, year, churchId, categoryGroup, categoryId]);

  useEffect(() => {
    void load();
  }, [load]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const visible = filterBy(items, search, [
    (entry) => entry.name,
    (entry) => entry.church ?? '',
    (entry) => entry.cellule ?? '',
  ]);

  const resetFilters = () => {
    setSearch('');
    setPeriod('month');
    setMonth(String(now.getMonth() + 1));
    setQuarter(String(Math.floor(now.getMonth() / 3) + 1));
    setYear(String(now.getFullYear()));
    setChurchId(user?.assigned_church_id ? String(user.assigned_church_id) : '');
    setCategoryGroup('');
    setCategoryId('');
  };

  const medals = ['🥇', '🥈', '🥉'];

  return (
    <Screen title="Top partenaires" subtitle={range || 'Classement par période'}>
      <FilterCard
        query={search}
        onQueryChange={setSearch}
        placeholder="Nom, église, cellule"
        onReset={resetFilters}
        active={Boolean(search || categoryGroup || categoryId) || period !== 'month' || Boolean(churchId)}
      >
        <SelectField
          label="Église"
          value={churchId}
          options={[
            { label: 'Toutes les églises accessibles', value: '' },
            ...[...churches].sort((a, b) => {
              if (a.id === user?.assigned_church_id) return -1;
              if (b.id === user?.assigned_church_id) return 1;
              return 0;
            }).map((c) => ({ label: c.name, value: String(c.id) })),
          ]}
          onChange={setChurchId}
          searchable
        />
        <SelectField label="Période" value={period} options={PERIODS} onChange={setPeriod} />
        <SelectField
          label="Famille de catégorie"
          value={categoryGroup}
          options={CATEGORY_GROUPS}
          onChange={(value) => { setCategoryGroup(value); setCategoryId(''); }}
        />
        <SelectField
          label="Catégorie"
          value={categoryId}
          options={[
            { label: 'Toutes les catégories', value: '' },
            ...categories
              .filter((category) => !categoryGroup || category.group === categoryGroup)
              .map((category) => ({ label: category.name, value: String(category.id) })),
          ]}
          onChange={setCategoryId}
          searchable
        />
        {period === 'month' ? (
          <SelectField label="Mois" value={month} options={MONTHS} onChange={setMonth} />
        ) : null}
        {period === 'quarter' ? (
          <SelectField label="Trimestre" value={quarter} options={QUARTERS} onChange={setQuarter} />
        ) : null}
        <SelectField
          label="Année"
          value={year}
          options={[
            { label: String(now.getFullYear() - 1), value: String(now.getFullYear() - 1) },
            { label: String(now.getFullYear()), value: String(now.getFullYear()) },
          ]}
          onChange={setYear}
        />
      </FilterCard>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && visible.length === 0 ? (
        <EmptyState message="Aucun don sur cette période." />
      ) : null}

      {!loading && !error && visible.length > 0 ? (
        <>
          <SectionTitle>{PERIODS.find((option) => option.value === period)?.label}</SectionTitle>
          {visible.map((entry, index) => (
            <Card key={entry.id} style={styles.card}>
              <View style={styles.row}>
                <View style={styles.rank}>
                  <Text style={styles.rankText}>{medals[index] ?? `#${index + 1}`}</Text>
                </View>
                <View style={styles.info}>
                  <Text style={styles.name} numberOfLines={1}>
                    {entry.name}{entry.is_user ? ' (compte)' : ''}
                  </Text>
                  <Text style={styles.meta} numberOfLines={1}>Église créditée : {entry.church ?? '—'}</Text>
                  {entry.origin_church && entry.origin_church !== entry.church ? (
                    <Text style={styles.meta} numberOfLines={1}>Église d'origine : {entry.origin_church}</Text>
                  ) : null}
                  <Text style={styles.meta} numberOfLines={1}>Cellule : {entry.cellule ?? '—'}</Text>
                  <Text style={styles.meta}>{entry.count} don(s)</Text>
                </View>
                <View style={styles.amountBox}>
                  <Text style={styles.amount}>{formatMoney(entry.amount)}</Text>
                </View>
              </View>
            </Card>
          ))}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { marginBottom: 8 },
  row: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  rank: { width: 36, alignItems: 'center', paddingTop: 2 },
  rankText: { fontSize: 16, fontWeight: '800', color: colors.blue },
  info: { flex: 1 },
  name: { fontSize: 15, fontWeight: '700', color: colors.ink },
  meta: { fontSize: 12, color: colors.muted, marginTop: 2 },
  amountBox: { alignItems: 'flex-end' },
  amount: { fontSize: 14, fontWeight: '800', color: colors.blue },
});
