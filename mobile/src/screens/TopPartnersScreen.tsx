import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import { FilterCard } from '../components/FilterCard';
import { filterBy } from '../utils/search';
import type { Partner } from '../api/types';
import {
  Badge,
  Card,
  colors,
  EmptyState,
  ErrorView,
  formatMoney,
  LoadingView,
  Screen,
  SelectField,
} from '../components/ui';

const PERIODS = [
  { label: 'Toutes les périodes', value: '' },
  { label: 'Ce mois-ci', value: 'month' },
  { label: 'Cette année', value: 'year' },
];

export function TopPartnersScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [partners, setPartners] = useState<Partner[]>([]);
  const [donations, setDonations] = useState<import('../api/types').Donation[]>([]);
  const [search, setSearch] = useState('');
  const [period, setPeriod] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [partnerData, donationData] = await Promise.all([
        api<{ data: Partner[] }>('/partners', { cacheKey: 'partners' }),
        api<{ data: import('../api/types').Donation[] }>('/donations', { cacheKey: 'donations' }),
      ]);
      setPartners(partnerData.data);
      setDonations(donationData.data);
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

    const totals = new Map<number, { amount: number; count: number }>();
    relevant.forEach((donation) => {
      const current = totals.get(donation.partner_id) ?? { amount: 0, count: 0 };
      current.amount += Number(donation.amount);
      current.count += 1;
      totals.set(donation.partner_id, current);
    });

    return partners
      .filter((partner) => (totals.get(partner.id)?.amount ?? 0) > 0)
      .map((partner) => ({
        partner,
        amount: totals.get(partner.id)?.amount ?? 0,
        count: totals.get(partner.id)?.count ?? 0,
      }))
      .sort((a, b) => b.amount - a.amount)
      .slice(0, 20);
  }, [partners, donations, period]);

  const visible = filterBy(ranked, search, [
    (entry) => entry.partner.full_name ?? '',
    (entry) => entry.partner.church ?? '',
  ]);

  const medals = ['🥇', '🥈', '🥉'];

  return (
    <Screen title="Top partenaires" subtitle="Les partenaires qui ont le plus donné">
      <FilterCard
        query={search}
        onQueryChange={setSearch}
        placeholder="Nom, église"
        onReset={() => { setSearch(''); setPeriod(''); }}
        active={Boolean(search || period)}
      >
        <SelectField label="Période" value={period} options={PERIODS} onChange={setPeriod} />
      </FilterCard>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && visible.length === 0 ? (
        <EmptyState message="Aucun partenaire avec don sur cette période." />
      ) : null}

      {!loading && !error ? visible.map((entry, index) => (
        <Card key={entry.partner.id} style={styles.card}>
          <View style={styles.row}>
            <View style={styles.rank}>
              <Text style={styles.rankText}>{medals[index] ?? `#${index + 1}`}</Text>
            </View>
            <View style={styles.info}>
              <Text style={styles.name} numberOfLines={1}>
                {entry.partner.full_name ?? `${entry.partner.first_name} ${entry.partner.last_name}`}
              </Text>
              <Text style={styles.meta} numberOfLines={1}>
                {entry.partner.church ?? '—'} · {entry.count} don(s)
              </Text>
            </View>
            <View style={styles.amountBox}>
              <Text style={styles.amount}>{formatMoney(entry.amount)}</Text>
              {entry.partner.is_user ? <Badge text="Compte" color={colors.blue} /> : null}
            </View>
          </View>
        </Card>
      )) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  card: { marginBottom: 8 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  rank: { width: 36, alignItems: 'center' },
  rankText: { fontSize: 16, fontWeight: '800', color: colors.blue },
  info: { flex: 1 },
  name: { fontSize: 15, fontWeight: '700', color: colors.ink },
  meta: { fontSize: 12, color: colors.muted, marginTop: 2 },
  amountBox: { alignItems: 'flex-end', gap: 4 },
  amount: { fontSize: 14, fontWeight: '800', color: colors.blue },
});
