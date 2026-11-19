import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api } from '../api/client';
import type { AppNotification, Church, Donation, Partner } from '../api/types';
import { useAuth } from '../context/AuthContext';
import { Badge, Card, colors, ErrorView, formatMoney, LoadingView, Screen, SectionTitle } from '../components/ui';

type Stat = { label: string; value: string; hint: string };

export function DashboardScreen({ navigation }: { navigation: { navigate: (name: string) => void } }) {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [stats, setStats] = useState<Stat[]>([]);
  const [unread, setUnread] = useState(0);

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [churches, donations, partners, notifications] = await Promise.all([
        api<{ data: Church[] }>('/churches'),
        api<{ data: Donation[] }>('/donations'),
        api<{ data: Partner[] }>('/partners'),
        api<{ data: AppNotification[]; unread_count: number }>('/notifications'),
      ]);

      const total = donations.data.reduce((sum, donation) => sum + Number(donation.amount), 0);
      setStats([
        { label: 'Églises', value: String(churches.data.length), hint: 'Églises accessibles' },
        { label: 'Partenaires', value: String(partners.data.length), hint: 'Enregistrés' },
        { label: 'Dons', value: String(donations.data.length), hint: formatMoney(total) },
        { label: 'Notifications', value: String(notifications.unread_count), hint: 'Non lues' },
      ]);
      setUnread(notifications.unread_count);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Impossible de charger le tableau de bord.');
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

  return (
    <Screen
      title="Tableau de bord"
      subtitle={user?.assigned_church?.name ?? 'ChurchManager'}
      actions={unread > 0 ? <Badge text={`${unread} non lue(s)`} color="#D4AF37" /> : undefined}
    >
      <Card>
        <Text style={styles.greeting}>Bonjour, {user?.name ?? ''}</Text>
        <Text style={styles.role}>{user?.role ?? 'Utilisateur'}</Text>
      </Card>

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        <>
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

          <SectionTitle>Actions rapides</SectionTitle>
          <View style={styles.actions}>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Dons')}>
              <Text style={styles.actionIcon}>＋</Text>
              <Text style={styles.actionText}>Enregistrer un don</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Rapports')}>
              <Text style={styles.actionIcon}>▤</Text>
              <Text style={styles.actionText}>Rapport de partenariat</Text>
            </Pressable>
            <Pressable style={styles.action} onPress={() => navigation.navigate('Partenaires')}>
              <Text style={styles.actionIcon}>♙</Text>
              <Text style={styles.actionText}>Partenaires</Text>
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
  greeting: { fontSize: 18, fontWeight: '700', color: colors.navy },
  role: { fontSize: 13, color: colors.muted, marginTop: 4 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  statCard: { width: '47%', minWidth: 140, marginBottom: 4 },
  statValue: { fontSize: 24, fontWeight: '800', color: colors.blue },
  statLabel: { fontSize: 14, fontWeight: '600', color: colors.ink, marginTop: 4 },
  statHint: { fontSize: 11, color: colors.muted, marginTop: 2 },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
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
