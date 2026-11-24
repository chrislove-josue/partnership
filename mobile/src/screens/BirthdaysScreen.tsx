import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { api, getApiUrl, getAuthToken, getErrorMessage } from '../api/client';
import { FilterCard } from '../components/FilterCard';
import { filterBy } from '../utils/search';
import { saveToDownloads } from '../native/mediaStore';
import type { BirthdayEntry } from '../api/types';
import {
  Badge,
  Card,
  colors,
  EmptyState,
  ErrorView,
  formatDate,
  LoadingView,
  PrimaryButton,
  Screen,
  SectionTitle,
  SelectField,
} from '../components/ui';

const MONTHS = [
  { label: 'Janvier', value: '1' },
  { label: 'Février', value: '2' },
  { label: 'Mars', value: '3' },
  { label: 'Avril', value: '4' },
  { label: 'Mai', value: '5' },
  { label: 'Juin', value: '6' },
  { label: 'Juillet', value: '7' },
  { label: 'Août', value: '8' },
  { label: 'Septembre', value: '9' },
  { label: 'Octobre', value: '10' },
  { label: 'Novembre', value: '11' },
  { label: 'Décembre', value: '12' },
];

export function BirthdaysScreen() {
  const now = new Date();
  const [month, setMonth] = useState(String(now.getMonth() + 1));
  const [year, setYear] = useState(String(now.getFullYear()));
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [birthdays, setBirthdays] = useState<BirthdayEntry[]>([]);
  const [search, setSearch] = useState('');
  const [downloading, setDownloading] = useState(false);

  const downloadPdf = async () => {
    setDownloading(true);
    try {
      const token = getAuthToken();
      const url = `${getApiUrl()}/birthdays/export?month=${month}&year=${year}`;
      const response = await fetch(url, {
        headers: token ? { Authorization: `Bearer ${token}` } : {},
      });
      if (!response.ok) {
        throw new Error('Téléchargement impossible.');
      }
      const blob = await response.blob();
      const base64 = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
          const result = String(reader.result);
          resolve(result.substring(result.indexOf(',') + 1));
        };
        reader.onerror = () => reject(new Error('Lecture du fichier impossible.'));
        reader.readAsDataURL(blob);
      });
      const fileName = `anniversaires_${year}-${String(month).padStart(2, '0')}.pdf`;
      await saveToDownloads(base64, fileName, 'application/pdf');
      Alert.alert('Téléchargé', `${fileName} enregistré dans Téléchargements.`);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Téléchargement impossible.'));
    } finally {
      setDownloading(false);
    }
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const response = await api<{ month: number; year: number; count: number; data: BirthdayEntry[] }>(
        `/birthdays?month=${month}&year=${year}`,
      );
      setBirthdays(response.data);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Impossible de charger les anniversaires.'));
    } finally {
      setLoading(false);
    }
  }, [month, year]);

  useEffect(() => {
    void load();
  }, [load]);

  const today = now.toISOString().slice(0, 10);
  const filtered = filterBy(birthdays, search, [
    (item) => item.name,
    (item) => item.church ?? '',
    (item) => item.cellule ?? '',
  ]);

  const grouped: Record<string, BirthdayEntry[]> = {};
  filtered.forEach((entry) => {
    (grouped[entry.day] ??= []).push(entry);
  });

  return (
    <Screen title="Anniversaires du mois" subtitle="Partenaires dont l'anniversaire tombe dans le mois">
      <FilterCard query={search} onQueryChange={setSearch} placeholder="Nom, église, cellule" onReset={() => setSearch('')} active={Boolean(search)}>
        <SelectField label="Mois" value={month} options={MONTHS} onChange={setMonth} />
        <SelectField
          label="Année"
          value={year}
          options={[
            { label: String(now.getFullYear() - 1), value: String(now.getFullYear() - 1) },
            { label: String(now.getFullYear()), value: String(now.getFullYear()) },
            { label: String(now.getFullYear() + 1), value: String(now.getFullYear() + 1) },
          ]}
          onChange={setYear}
        />
      </FilterCard>

      <PrimaryButton
        title={downloading ? 'Téléchargement…' : 'Télécharger en PDF'}
        onPress={() => void downloadPdf()}
        loading={downloading}
        disabled={downloading}
      />

      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}
      {!loading && !error && filtered.length === 0 ? (
        <EmptyState message="Aucun anniversaire ce mois-ci pour vos églises." />
      ) : null}

      {!loading && !error ? (
        Object.keys(grouped)
          .sort((a, b) => Number(a) - Number(b))
          .map((day) => (
            <Card key={day}>
              <SectionTitle>
                {Number(day) === now.getDate() && Number(month) === now.getMonth() + 1 && Number(year) === now.getFullYear()
                  ? '🎂 Aujourd’hui — '
                  : ''}
                {Number(day)} {MONTHS[Number(month) - 1]?.label ?? ''}
              </SectionTitle>
              {grouped[day].map((entry) => (
                <View key={entry.name + entry.date} style={styles.row}>
                  <View style={styles.left}>
                    <Text style={styles.name}>{entry.name}</Text>
                    <Text style={styles.meta}>
                      {entry.church ?? '—'} {entry.cellule ? `· ${entry.cellule}` : ''}
                    </Text>
                    {entry.phone ? <Text style={styles.meta}>Tél : {entry.phone}</Text> : null}
                  </View>
                  <View style={styles.right}>
                    {entry.age !== null ? <Badge text={`${entry.age} ans`} /> : null}
                    {entry.is_user ? <Badge text="Compte" color={colors.blue} /> : null}
                  </View>
                </View>
              ))}
            </Card>
          ))
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  left: { flex: 1, paddingRight: 8 },
  right: { flexDirection: 'row', gap: 4, flexWrap: 'wrap', justifyContent: 'flex-end' },
  name: { fontSize: 15, fontWeight: '700', color: colors.ink },
  meta: { fontSize: 12, color: colors.muted, marginTop: 2 },
});
