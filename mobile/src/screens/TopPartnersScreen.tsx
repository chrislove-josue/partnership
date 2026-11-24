import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import { FilterCard } from '../components/FilterCard';
import { filterBy } from '../utils/search';
import type { Cellule, Church, Donation, Partner } from '../api/types';
import {
  Badge,
  Card,
  colors,
  EmptyState,
  ErrorView,
  formatMoney,
  LoadingView,
  Screen,
  SectionTitle,
  SelectField,
} from '../components/ui';

const PERIODS = [
  { label: 'Toutes les périodes', value: '' },
  { label: 'Ce mois-ci', value: 'month' },
  { label: 'Cette année', value: 'year' },
];

const GROUPINGS = [
  { label: 'Par partenaire', value: 'partner' },
  { label: 'Par église', value: 'church' },
  { label: 'Par cellule', value: 'cellule' },
];

type RankedEntry = {
  id: string;
  name: string;
  church: string | null;
  cellule: string | null;
  amount: number;
  count: number;
  isUser: boolean;
};

export function TopPartnersScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [partners, setPartners] = useState<Partner[]>([]);
  const [donations, setDonations] = useState<Donation[]>([]);
  const [churches, setChurches] = useState<Church[]>([]);
  const [cellules, setCellules] = useState<Cellule[]>([]);
  const [search, setSearch] = useState('');
  const [period, setPeriod] = useState('');
  const [groupBy, setGroupBy] = useState('partner');
  const [churchFilter, setChurchFilter] = useState('');
  const [celluleFilter, setCelluleFilter] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [partnerData, donationData, churchData, celluleData] = await Promise.all([
        api<{ data: Partner[] }>('/partners', { cacheKey: 'partners' }),
        api<{ data: Donation[] }>('/donations', { cacheKey: 'donations' }),
        api<{ data: Church[] }>('/churches', { cacheKey: 'churches' }),
        api<{ data: Cellule[] }>('/cellules', { cacheKey: 'cellules' }),
      ]);
      setPartners(partnerData.data);
      setDonations(donationData.data);
      setChurches(churchData.data);
      setCellules(celluleData.data);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Impossible de charger les top partenaires.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const ranked = useMemo(() => {
    const now = new Date();
    const relevant = donations.filter((donation) => {
      if (period === '') return true;
      const date = new Date(donation.date);
      if (Number.isNaN(date.getTime())) return false;
      if (period === 'month') return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
      if (period === 'year') return date.getFullYear() === now.getFullYear();
      return true;
    });

    const partnerById = new Map(partners.map((partner) => [partner.id, partner]));
    const churchById = new Map(churches.map((church) => [church.id, church.name]));
    const celluleById = new Map(cellules.map((cellule) => [cellule.id, cellule.name]));

    const buckets = new Map<string, RankedEntry>();

    relevant.forEach((donation) => {
      const partner = partnerById.get(donation.partner_id);
      const churchName = partner?.church ?? churchById.get(donation.church_id) ?? null;
      const celluleName = partner?.cellule ?? celluleById.get(partner?.cellule_id ?? 0) ?? null;

      let key: string;
      let name: string;

      if (groupBy === 'church') {
        key = `church:${donation.church_id}`;
        name = churchById.get(donation.church_id) ?? churchName ?? 'Église inconnue';
      } else if (groupBy === 'cellule') {
        const celluleId = partner?.cellule_id ?? 0;
        key = `cellule:${celluleId}`;
        name = celluleById.get(celluleId) ?? celluleName ?? 'Sans cellule';
      } else {
        key = `partner:${donation.partner_id}`;
        name: (partner?.full_name
          ?? `${partner?.first_name ?? ''} ${partner?.last_name ?? ''}`.trim())
          || 'Partenaire inconnu',
      }

      const current = buckets.get(key) ?? {
        id: key,
        name,
        church: churchName,
        cellule: celluleName,
        amount: 0,
        count: 0,
        isUser: Boolean(partner?.is_user),
      };
      current.amount += Number(donation.amount);
      current.count += 1;
      buckets.set(key, current);
    });

    return [...buckets.values()]
      .filter((entry) => {
        if (churchFilter && entry.church !== churchById.get(Number(churchFilter))) return false;
        if (celluleFilter && entry.cellule !== celluleById.get(Number(celluleFilter))) return false;
        return true;
      })
      .sort((a, b) => b.amount - a.amount)
      .slice(0, 30);
  }, [partners, donations, churches, cellules, period, groupBy, churchFilter, celluleFilter]);

  const visible = filterBy(ranked, search, [
    (entry) => entry.name,
    (entry) => entry.church ?? '',
    (entry) => entry.cellule ?? '',
  ]);

  const resetFilters = () => {
    setSearch('');
    setPeriod('');
    setGroupBy('partner');
    setChurchFilter('');
    setCelluleFilter('');
  };

  const medals = ['🥇', '🥈', '🥉'];

  return (
    <Screen title="Top partenaires" subtitle="Classement par partenaire, église ou cellule">
      <FilterCard
        query={search}
        onQueryChange={setSearch}
        placeholder="Nom, église, cellule"
        onReset={resetFilters}
        active={Boolean(search || period || churchFilter || celluleFilter || groupBy !== 'partner')}
      >
        <SelectField label="Grouper par" value={groupBy} options={GROUPINGS} onChange={setGroupBy} />
        <SelectField label="Période" value={period} options={PERIODS} onChange={setPeriod} />
        <SelectField
          label="Église"
          value={churchFilter}
          options={[{ label: 'Toutes les églises', value: '' }, ...churches.map((c) => ({ label: c.name, value: String(c.id) }))]}
          onChange={setChurchFilter}
          searchable
        />
        <SelectField
          label="Cellule"
          value={celluleFilter}
          options={[{ label: 'Toutes les cellules', value: '' }, ...cellules.map((c) => ({ label: c.name, value: String(c.id) }))]}
          onChange={setCelluleFilter}
          searchable
        />
      </FilterCard>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && visible.length === 0 ? (
        <EmptyState message="Aucun don sur ces filtres." />
      ) : null}

      {!loading && !error && visible.length > 0 ? (
        <>
          <SectionTitle>
            {GROUPINGS.find((option) => option.value === groupBy)?.label ?? 'Classement'}
          </SectionTitle>
          {visible.map((entry, index) => (
            <Card key={entry.id} style={styles.card}>
              <View style={styles.row}>
                <View style={styles.rank}>
                  <Text style={styles.rankText}>{medals[index] ?? `#${index + 1}`}</Text>
                </View>
                <View style={styles.info}>
                  <Text style={styles.name} numberOfLines={1}>
                    {entry.name}
                    {entry.isUser && groupBy === 'partner' ? ' (compte)' : ''}
                  </Text>
                  <Text style={styles.meta} numberOfLines={1}>Église : {entry.church ?? '—'}</Text>
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
