import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import { FilterCard } from '../components/FilterCard';
import { filterBy } from '../utils/search';
import type { AppNotification, Church, Donation, Partner } from '../api/types';
import { useAuth } from '../context/AuthContext';
import {
  Badge,
  Card,
  colors,
  ErrorView,
  formatDate,
  formatMoney,
  LoadingView,
  Screen,
  SectionTitle,
} from '../components/ui';

type Stat = { label: string; value: string; hint: string };
type CategoryTotal = { name: string; color: string; amount: number; count: number };

/**
 * L’église de groupe cumule les trois rôles : elle est à la fois groupe,
 * sous-groupe et église locale (cas de CE SIKECODJI). Les autres églises
 * conservent le type enregistré dans la base.
 */
function countChurchRoles(churches: Church[]) {
  const roles = { GROUP: 0, SUB_GROUP: 0, LOCAL: 0 };

  churches.forEach((church) => {
    if (church.type === 'GROUP') {
      roles.GROUP += 1;
      roles.SUB_GROUP += 1;
      roles.LOCAL += 1;
      return;
    }
    if (church.type in roles) {
      roles[church.type as keyof typeof roles] += 1;
    }
  });

  return roles;
}

export function DashboardScreen({ navigation }: { navigation: { navigate: (name: string) => void } }) {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [churches, setChurches] = useState<Church[]>([]);
  const [donations, setDonations] = useState<Donation[]>([]);
  const [partners, setPartners] = useState<Partner[]>([]);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unread, setUnread] = useState(0);
  const [search, setSearch] = useState('');
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async (refresh = false) => {
    setLoading(true);
    setError('');
    try {
      const [churchResponse, donationResponse, partnerResponse, notificationResponse] = await Promise.all([
        api<{ data: Church[] }>('/churches', { cacheKey: 'churches', refresh }),
        api<{ data: Donation[] }>('/donations', { cacheKey: 'donations', refresh }),
        api<{ data: Partner[] }>('/partners', { cacheKey: 'partners', refresh }),
        api<{ data: AppNotification[]; unread_count: number }>('/notifications', {
          cacheKey: `notifications:${user?.id ?? 'me'}`,
          refresh,
        }),
      ]);

      setChurches(churchResponse.data);
      setDonations(donationResponse.data);
      setPartners(partnerResponse.data);
      setNotifications(notificationResponse.data);
      setUnread(notificationResponse.unread_count);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Impossible de charger le tableau de bord.'));
    } finally {
      setLoading(false);
    }
  }, [user?.id]);

  useEffect(() => {
    void load();
  }, [load]);

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  /** Force une lecture en ligne : aucun cache ni données locales ne sont utilisés. */
  const refreshOnline = useCallback(async () => {
    setRefreshing(true);
    try {
      await load(true);
    } finally {
      setRefreshing(false);
    }
  }, [load]);

  const summary = useMemo(() => {
    const total = donations.reduce((sum, donation) => sum + Number(donation.amount), 0);

    // Le pasteur de groupe/sous-groupe voit d'abord le total de SA église,
    // puis le total de toutes les églises accessibles.
    const assignedChurchId = user?.assigned_church_id;
    const ownDonations = assignedChurchId
      ? donations.filter((donation) => donation.church_id === assignedChurchId)
      : donations;
    const ownTotal = ownDonations.reduce((sum, donation) => sum + Number(donation.amount), 0);
    const ownChurch = churches.find((church) => church.id === assignedChurchId);

    const role = String(user?.role ?? '');
    const showBothTotals = ['GROUP_PASTOR', 'SUBGROUP_PASTOR'].includes(role)
      || ['GROUP_PASTOR', 'SUBGROUP_PASTOR'].some((r) => (user?.roles ?? []).includes(r));
    const now = new Date();
    const month = now.getMonth();
    const year = now.getFullYear();
    const monthDonations = donations.filter((donation) => {
      const date = new Date(donation.date);
      return !Number.isNaN(date.getTime()) && date.getMonth() === month && date.getFullYear() === year;
    });
    const monthTotal = monthDonations.reduce((sum, donation) => sum + Number(donation.amount), 0);

    const partnerIdsWithDonation = new Set(donations.map((donation) => donation.partner_id));

    const categoryMap = new Map<string, CategoryTotal>();
    donations.forEach((donation) => {
      const name = donation.category || 'Autre';
      const current = categoryMap.get(name) ?? {
        name,
        color: donation.color || colors.blue,
        amount: 0,
        count: 0,
      };
      current.amount += Number(donation.amount);
      current.count += 1;
      categoryMap.set(name, current);
    });
    const categories = [...categoryMap.values()]
      .sort((a, b) => b.amount - a.amount)
      .slice(0, 4);

    const churchTypes = countChurchRoles(churches);

    const recentDonations = [...donations]
      .sort((a, b) => String(b.date).localeCompare(String(a.date)))
      .slice(0, 5);

    const recentNotifications = [...notifications]
      .sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)))
      .slice(0, 3);

    return {
      total,
      ownTotal,
      ownChurchName: ownChurch?.name ?? null,
      showBothTotals,
      monthTotal,
      monthCount: monthDonations.length,
      partnersWithDonation: partnerIdsWithDonation.size,
      categories,
      churchTypes,
      recentDonations,
      recentNotifications,
    };
  }, [churches, donations, partners, notifications, user?.role, user?.roles, user?.assigned_church_id]);

  const stats: Stat[] = [
    {
      label: 'Églises',
      value: String(churches.length),
      hint: `${summary.churchTypes.LOCAL} locales`,
    },
    {
      label: 'Partenaires',
      value: String(partners.length),
      hint: `${summary.partnersWithDonation} avec don`,
    },
    { label: 'Dons', value: String(donations.length), hint: formatMoney(summary.total) },
    { label: 'Notifications', value: String(unread), hint: 'Non lues' },
  ];

  const maxCategoryAmount = Math.max(1, ...summary.categories.map((category) => category.amount));

  const visibleDonations = filterBy(summary.recentDonations, search, [
    (donation) => donation.partner,
    (donation) => donation.category,
    (donation) => donation.church,
    (donation) => donation.amount,
    (donation) => donation.date,
  ]);
  const visibleCategories = filterBy(summary.categories, search, [
    (category) => category.name,
    (category) => category.amount,
  ]);
  const visibleNotifications = filterBy(summary.recentNotifications, search, [
    (notification) => notification.title,
    (notification) => notification.message,
    (notification) => notification.created_at,
  ]);
  const visibleMaxCategoryAmount = Math.max(1, ...visibleCategories.map((category) => category.amount));

  return (
    <Screen
      title="Tableau de bord"
      subtitle={typeof user?.assigned_church === 'string'
        ? user.assigned_church
        : user?.assigned_church?.name ?? 'ChurchManager'}
      actions={unread > 0 ? <Badge text={`${unread} non lue(s)`} color={colors.gold} /> : undefined}
    >
      <Card style={styles.hero}>
        <Text style={styles.greeting}>Bonjour, {user?.name ?? ''}</Text>
        <Text style={styles.role}>{user?.role ?? 'Utilisateur'}</Text>
        <View style={styles.heroTotal}>
          <View style={styles.heroTotalHeader}>
            <Text style={styles.heroTotalLabel}>Total général des dons</Text>
            <Pressable
              style={[styles.heroRefresh, (loading || refreshing) && styles.heroRefreshDisabled]}
              onPress={() => void refreshOnline()}
              disabled={loading || refreshing}
              accessibilityRole="button"
              accessibilityLabel="Actualiser les dons depuis le serveur"
            >
              <Text style={styles.heroRefreshText}>
                {refreshing ? 'Actualisation…' : '↻ Actualiser'}
              </Text>
            </Pressable>
          </View>
          <Text style={styles.heroTotalValue}>{formatMoney(summary.showBothTotals ? summary.ownTotal : summary.total)}</Text>
          <Text style={styles.heroTotalHint}>
            {summary.showBothTotals
              ? `Sur ${summary.ownChurchName ?? 'votre église'}`
              : 'Sur toutes les églises accessibles'}
          </Text>
          {summary.showBothTotals ? (
            <View style={styles.heroTotalSecondary}>
              <Text style={styles.heroTotalSecondaryLabel}>Total sur toutes les églises accessibles</Text>
              <Text style={styles.heroTotalSecondaryValue}>{formatMoney(summary.total)}</Text>
            </View>
          ) : null}
        </View>
      </Card>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        <>
          <FilterCard
            query={search}
            onQueryChange={setSearch}
            placeholder="Don, catégorie, notification"
            onReset={() => setSearch('')}
            active={Boolean(search)}
          />

          <SectionTitle>Aperçu</SectionTitle>
          <View style={styles.grid}>
            {stats.map((stat) => (
              <Card key={stat.label} style={styles.statCard}>
                <Text style={styles.statValue}>{stat.value}</Text>
                <Text style={styles.statLabel}>{stat.label}</Text>
                <Text style={styles.statHint} numberOfLines={1}>{stat.hint}</Text>
              </Card>
            ))}
          </View>

          <Card style={styles.monthCard}>
            <View style={styles.monthHeader}>
              <View style={styles.monthInfo}>
                <Text style={styles.monthLabel}>Dons de ce mois</Text>
                <Text style={styles.monthValue}>{formatMoney(summary.monthTotal)}</Text>
                <Text style={styles.monthHint}>{summary.monthCount} don(s) enregistré(s)</Text>
              </View>
              <Pressable style={styles.monthButton} onPress={() => navigation.navigate('Rapports')}>
                <Text style={styles.monthButtonText}>Voir le rapport</Text>
              </Pressable>
            </View>
          </Card>

          <SectionTitle>Derniers dons</SectionTitle>
          <Card style={styles.listCard}>
            {visibleDonations.length === 0 ? (
              <Text style={styles.empty}>Aucun don trouvé.</Text>
            ) : visibleDonations.map((donation, index) => (
              <Pressable
                key={donation.id}
                style={[styles.listRow, index > 0 && styles.listRowBorder]}
                onPress={() => navigation.navigate('Dons')}
              >
                <View style={styles.listMain}>
                  <Text style={styles.listTitle} numberOfLines={1}>{donation.partner ?? 'Partenaire'}</Text>
                  <Text style={styles.listSub} numberOfLines={1}>
                    {donation.category ?? 'Catégorie'} · {formatDate(donation.date)} · {donation.church ?? ''}
                  </Text>
                </View>
                <Text style={styles.listAmount}>{formatMoney(Number(donation.amount))}</Text>
              </Pressable>
            ))}
          </Card>

          <SectionTitle>Principales catégories</SectionTitle>
          <Card style={styles.listCard}>
            {visibleCategories.length === 0 ? (
              <Text style={styles.empty}>Aucune catégorie trouvée.</Text>
            ) : visibleCategories.map((category, index) => (
              <View key={category.name} style={[styles.categoryRow, index > 0 && styles.listRowBorder]}>
                <View style={styles.categoryHead}>
                  <Text style={styles.categoryName} numberOfLines={1}>{category.name}</Text>
                  <Text style={styles.categoryAmount}>{formatMoney(category.amount)}</Text>
                </View>
                <View style={styles.barTrack}>
                  <View
                    style={[
                      styles.barFill,
                      { backgroundColor: category.color || colors.blue, width: `${Math.max(6, (category.amount / visibleMaxCategoryAmount) * 100)}%` },
                    ]}
                  />
                </View>
                <Text style={styles.categoryCount}>{category.count} don(s)</Text>
              </View>
            ))}
          </Card>

          <SectionTitle>Dernières notifications</SectionTitle>
          <Card style={styles.listCard}>
            {visibleNotifications.length === 0 ? (
              <Text style={styles.empty}>Aucune notification trouvée.</Text>
            ) : visibleNotifications.map((notification, index) => (
              <Pressable
                key={notification.id}
                style={[styles.listRow, index > 0 && styles.listRowBorder]}
                onPress={() => navigation.navigate('Notifications')}
              >
                <View style={styles.listMain}>
                  <Text style={styles.listTitle} numberOfLines={1}>
                    {notification.is_read ? '' : '● '}{notification.title}
                  </Text>
                  <Text style={styles.listSub} numberOfLines={1}>{notification.message}</Text>
                </View>
                <Text style={styles.listDate}>{formatDate(notification.created_at)}</Text>
              </Pressable>
            ))}
          </Card>

          <SectionTitle>Répartition des églises</SectionTitle>
          <View style={styles.churchRow}>
            <Card style={styles.churchCard}>
              <Text style={styles.churchValue}>{summary.churchTypes.GROUP}</Text>
              <Text style={styles.churchLabel}>Groupe</Text>
            </Card>
            <Card style={styles.churchCard}>
              <Text style={styles.churchValue}>{summary.churchTypes.SUB_GROUP}</Text>
              <Text style={styles.churchLabel}>Sous-groupe</Text>
            </Card>
            <Card style={styles.churchCard}>
              <Text style={styles.churchValue}>{summary.churchTypes.LOCAL}</Text>
              <Text style={styles.churchLabel}>Locale</Text>
            </Card>
          </View>
          <Text style={styles.churchNote}>
            L’église de groupe est aussi comptée comme sous-groupe et comme église locale.
          </Text>

          <SectionTitle>Actions rapides</SectionTitle>
          <View style={styles.actions}>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Dons')}>
              <Text style={styles.actionIcon}>＋</Text>
              <Text style={styles.actionText}>Enregistrer un don</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Dons')}>
              <Text style={styles.actionIcon}>⇩</Text>
              <Text style={styles.actionText}>Importer des dons</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Partenaires')}>
              <Text style={styles.actionIcon}>♙</Text>
              <Text style={styles.actionText}>Partenaires</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Rapports')}>
              <Text style={styles.actionIcon}>▤</Text>
              <Text style={styles.actionText}>Rapport de partenariat</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('HistoriqueConnexion')}>
              <Text style={styles.actionIcon}>◷</Text>
              <Text style={styles.actionText}>Historique de connexion</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Anniversaires')}>
              <Text style={styles.actionIcon}>🎂</Text>
              <Text style={styles.actionText}>Anniversaires du mois</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Administration')}>
              <Text style={styles.actionIcon}>⚙</Text>
              <Text style={styles.actionText}>Administration</Text>
            </Pressable>
          </View>
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: { marginBottom: 4 },
  greeting: { fontSize: 18, fontWeight: '700', color: colors.navy },
  role: { fontSize: 13, color: colors.muted, marginTop: 4 },
  heroTotal: {
    marginTop: 16,
    paddingTop: 14,
    borderTopWidth: 1,
    borderTopColor: colors.border,
  },
  heroTotalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 },
  heroTotalLabel: { fontSize: 12, color: colors.muted, flex: 1 },
  heroRefresh: {
    backgroundColor: colors.lightBlue,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  heroRefreshDisabled: { opacity: 0.6 },
  heroRefreshText: { color: colors.blue, fontWeight: '700', fontSize: 12 },
  heroTotalValue: { fontSize: 26, fontWeight: '800', color: colors.blue, marginTop: 2 },
  heroTotalHint: { fontSize: 11, color: colors.muted, marginTop: 2 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  statCard: { width: '47%', minWidth: 140, marginBottom: 4 },
  statValue: { fontSize: 24, fontWeight: '800', color: colors.blue },
  statLabel: { fontSize: 14, fontWeight: '600', color: colors.ink, marginTop: 4 },
  statHint: { fontSize: 11, color: colors.muted, marginTop: 2 },
  monthCard: { marginTop: 4 },
  monthHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10 },
  monthInfo: { flex: 1 },
  monthLabel: { fontSize: 12, color: colors.muted },
  monthValue: { fontSize: 20, fontWeight: '800', color: colors.navy, marginTop: 2 },
  monthHint: { fontSize: 11, color: colors.muted, marginTop: 2 },
  monthButton: {
    backgroundColor: colors.lightBlue,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  monthButtonText: { color: colors.blue, fontWeight: '700', fontSize: 12 },
  listCard: { paddingVertical: 4 },
  listRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 12 },
  listRowBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  listMain: { flex: 1 },
  listTitle: { fontSize: 14, fontWeight: '700', color: colors.ink },
  listSub: { fontSize: 11, color: colors.muted, marginTop: 3 },
  listAmount: { fontSize: 13, fontWeight: '800', color: colors.blue },
  listDate: { fontSize: 11, color: colors.muted },
  empty: { fontSize: 13, color: colors.muted, paddingVertical: 12 },
  categoryRow: { paddingVertical: 12 },
  categoryHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 10 },
  categoryName: { fontSize: 13, fontWeight: '700', color: colors.ink, flex: 1 },
  categoryAmount: { fontSize: 12, fontWeight: '800', color: colors.blue },
  barTrack: {
    height: 6,
    borderRadius: 3,
    backgroundColor: colors.lightBlue,
    marginTop: 8,
    overflow: 'hidden',
  },
  barFill: { height: 6, borderRadius: 3 },
  categoryCount: { fontSize: 11, color: colors.muted, marginTop: 5 },
  churchRow: { flexDirection: 'row', gap: 10 },
  churchCard: { flex: 1, alignItems: 'center', paddingVertical: 14 },
  churchValue: { fontSize: 20, fontWeight: '800', color: colors.blue },
  churchLabel: { fontSize: 11, color: colors.muted, marginTop: 3, textAlign: 'center' },
  churchNote: { fontSize: 11, color: colors.muted, marginTop: 6, lineHeight: 16 },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 14 },
  action: {
    width: '47%',
    backgroundColor: colors.surface,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    padding: 14,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  actionIcon: { color: colors.blue, fontSize: 22, fontWeight: '700' },
  actionText: { color: colors.ink, fontSize: 13, fontWeight: '600', flex: 1 },
});
