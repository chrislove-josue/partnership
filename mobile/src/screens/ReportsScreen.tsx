import React, { useCallback, useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import * as Sharing from 'expo-sharing';
import { api, apiDownload, getErrorMessage } from '../api/client';
import type { Church, PartnershipReport, RecipientsPreview } from '../api/types';
import {
  Badge,
  Card,
  CheckboxField,
  colors,
  ConfirmModal,
  EmptyState,
  ErrorView,
  formatDate,
  formatMoney,
  LoadingView,
  PrimaryButton,
  Screen,
  SectionTitle,
  SelectField,
} from '../components/ui';

const months = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

export function ReportsScreen() {
  const [loadingChurches, setLoadingChurches] = useState(true);
  const [churches, setChurches] = useState<Church[]>([]);
  const [churchId, setChurchId] = useState('');
  const [type, setType] = useState('month');
  const [month, setMonth] = useState(String(new Date().getMonth() + 1));
  const [quarter, setQuarter] = useState(String(Math.floor(new Date().getMonth() / 3) + 1));
  const [year, setYear] = useState(String(new Date().getFullYear()));
  const [report, setReport] = useState<PartnershipReport | null>(null);
  const [preview, setPreview] = useState<RecipientsPreview | null>(null);
  const [selectedCc, setSelectedCc] = useState<string[]>([]);
  const [confirming, setConfirming] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const response = await api<{ data: Church[] }>('/churches', { cacheKey: 'churches' });
        setChurches(response.data);
        setChurchId((current) => current || response.data[0]?.id.toString() || '');
      } catch (caught) {
        setError(getErrorMessage(caught, 'Impossible de charger les églises.'));
      } finally {
        setLoadingChurches(false);
      }
    })();
  }, []);

  const query = useCallback(() => {
    const params = new URLSearchParams({
      church_id: churchId,
      type,
      year,
    });
    if (type === 'month') {
      params.set('month', month);
    }
    if (type === 'quarter') {
      params.set('quarter', quarter);
    }
    return params.toString();
  }, [churchId, type, year, month, quarter]);

  const generate = async () => {
    if (!churchId) {
      return;
    }
    setLoading(true);
    setError('');
    setReport(null);
    try {
      setReport(await api<PartnershipReport>(`/reports/partnership?${query()}`));
    } catch (caught) {
      setError(getErrorMessage(caught, 'Génération impossible.'));
    } finally {
      setLoading(false);
    }
  };

  const prepareSend = async () => {
    if (!churchId) {
      return;
    }
    setLoading(true);
    setError('');
    try {
      const result = await api<RecipientsPreview>(`/reports/partnership/recipients?${query()}`);
      setPreview(result);
      setSelectedCc(result.cc.map((recipient) => recipient.email));
      setConfirming(true);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Aperçu des destinataires impossible.'));
    } finally {
      setLoading(false);
    }
  };

  const send = async () => {
    setLoading(true);
    setError('');
    try {
      const params = new URLSearchParams(query());
      const body: Record<string, unknown> = {
        church_id: Number(params.get('church_id')),
        type: params.get('type'),
        year: Number(params.get('year')),
        month: params.get('month') ? Number(params.get('month')) : null,
        quarter: params.get('quarter') ? Number(params.get('quarter')) : null,
        confirm: true,
        cc_emails: selectedCc,
      };
      const result = await api<{ sent: boolean; to: unknown[]; cc: unknown[] }>('/reports/partnership/send', {
        method: 'POST',
        body,
      });
      setConfirming(false);
      Alert.alert('Rapport envoyé', `${result.to.length} destinataire(s), ${result.cc.length} en copie.`);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Envoi impossible.'));
    } finally {
      setLoading(false);
    }
  };

  const exportReport = async () => {
    if (!churchId) {
      return;
    }
    setLoading(true);
    setError('');
    try {
      const fileName = `rapport-${churchId}-${type}-${year}.xlsx`;
      const uri = await apiDownload(`/reports/partnership/export?${query()}`, fileName);
      await Sharing.shareAsync(uri, { mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    } catch (caught) {
      setError(getErrorMessage(caught, 'Export impossible.'));
    } finally {
      setLoading(false);
    }
  };

  const churchOptions = churches.map((church) => ({ label: church.name, value: String(church.id) }));
  const yearOptions = Array.from({ length: 7 }, (_, index) => {
    const value = String(new Date().getFullYear() - 5 + index);
    return { label: value, value };
  });

  return (
    <Screen title="Rapport de partenariat" subtitle="Générez, exportez et envoyez à la hiérarchie">
      <Card>
        {loadingChurches ? <LoadingView /> : null}
        <SelectField label="Église" value={churchId} options={churchOptions} onChange={setChurchId} />
        <SelectField
          label="Période"
          value={type}
          options={[
            { label: 'Par mois', value: 'month' },
            { label: 'Par trimestre', value: 'quarter' },
            { label: 'Par année', value: 'year' },
          ]}
          onChange={setType}
        />
        {type === 'month' ? (
          <SelectField
            label="Mois"
            value={month}
            options={months.map((label, index) => ({ label, value: String(index + 1) }))}
            onChange={setMonth}
          />
        ) : null}
        {type === 'quarter' ? (
          <SelectField
            label="Trimestre"
            value={quarter}
            options={[1, 2, 3, 4].map((value) => ({ label: `T${value}`, value: String(value) }))}
            onChange={setQuarter}
          />
        ) : null}
        <SelectField label="Année" value={year} options={yearOptions} onChange={setYear} />
        <View style={styles.buttonRow}>
          <PrimaryButton title="Générer" onPress={() => void generate()} disabled={!churchId || loading} />
          <PrimaryButton title="Exporter" onPress={() => void exportReport()} disabled={!churchId || loading} />
        </View>
      </Card>

      {error ? <ErrorView message={error} /> : null}
      {loading && !report ? <LoadingView /> : null}
      {!loading && !report && !error ? <EmptyState message="Choisissez une église puis générez le rapport." /> : null}

      {report ? (
        <>
          <Card>
            <SectionTitle>{report.church.name}</SectionTitle>
            <Badge text={report.period.label} />
            <Text style={styles.total}>{formatMoney(report.totals.amount)}</Text>
            <Text style={styles.meta}>
              {report.totals.donationCount} don(s) · {report.totals.partnersWithDonation} partenaire(s) avec don
            </Text>
            <PrimaryButton title="Préparer l’envoi" onPress={() => void prepareSend()} loading={loading} disabled={loading} />
          </Card>

          <Card>
            <SectionTitle>Par catégorie</SectionTitle>
            {report.categories.length === 0 ? <Text style={styles.meta}>Aucune catégorie pour cette période.</Text> : null}
            {report.categories.map((category) => (
              <View key={category.name} style={styles.listRow}>
                <View style={[styles.color, { backgroundColor: category.color }]} />
                <Text style={styles.listLabel}>{category.name}</Text>
                <Text style={styles.listValue}>{formatMoney(category.amount)}</Text>
              </View>
            ))}
          </Card>

          <Card>
            <SectionTitle>Partenaires</SectionTitle>
            {report.partners.length === 0 ? <Text style={styles.meta}>Aucun partenaire avec don.</Text> : null}
            {report.partners.map((partner) => (
              <View key={partner.name} style={styles.listRow}>
                <Text style={styles.listLabel}>{partner.name}</Text>
                <Text style={styles.listValue}>{formatMoney(partner.amount)}</Text>
              </View>
            ))}
          </Card>

          {report.partnersWithoutDonation.length > 0 ? (
            <Card>
              <SectionTitle>Sans don sur la période</SectionTitle>
              {report.partnersWithoutDonation.map((partner) => (
                <Text key={partner.name} style={styles.meta}>{partner.name}</Text>
              ))}
            </Card>
          ) : null}

          <Card>
            <SectionTitle>Détail des dons</SectionTitle>
            <ScrollView horizontal>
              <View>
                {report.donations.map((donation) => (
                  <View key={`${donation.date}-${donation.partner}-${donation.amount}`} style={styles.listRow}>
                    <Text style={styles.listLabel}>{donation.partner} · {donation.category}</Text>
                    <Text style={styles.listValue}>{formatMoney(donation.amount)}</Text>
                    <Text style={styles.meta}>{formatDate(donation.date)}</Text>
                  </View>
                ))}
              </View>
            </ScrollView>
          </Card>
        </>
      ) : null}

      <ConfirmModal
        visible={confirming}
        title="Envoyer le rapport à la hiérarchie ?"
        message={preview
          ? `${preview.church.name} · ${preview.period.label} · ${formatMoney(preview.totals.amount)} · À : ${preview.recipients.map((recipient) => recipient.email).join(', ')}`
          : 'Confirmez l’envoi.'}
        confirmLabel="Confirmer l’envoi"
        onCancel={() => setConfirming(false)}
        onConfirm={() => void send()}
      >
        {preview && preview.cc.length > 0 ? (
          <View style={styles.ccBox}>
            <Text style={styles.ccTitle}>Copies — destinataires à conserver</Text>
            {preview.cc.map((recipient) => (
              <CheckboxField
                key={recipient.email}
                label={recipient.name ? `${recipient.name} <${recipient.email}>` : recipient.email}
                checked={selectedCc.includes(recipient.email)}
                onChange={(checked) => setSelectedCc((current) => checked
                  ? [...current, recipient.email]
                  : current.filter((email) => email !== recipient.email))}
              />
            ))}
          </View>
        ) : null}
      </ConfirmModal>
    </Screen>
  );
}

const styles = StyleSheet.create({
  buttonRow: { flexDirection: 'row', gap: 10, marginTop: 6 },
  total: { fontSize: 26, fontWeight: '800', color: colors.blue, marginTop: 12 },
  meta: { fontSize: 13, color: colors.muted, marginTop: 5 },
  listRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.border },
  color: { width: 10, height: 10, borderRadius: 5 },
  listLabel: { flex: 1, color: colors.ink, fontSize: 14 },
  listValue: { fontWeight: '700', color: colors.blue, fontSize: 14 },
  ccBox: { marginVertical: 8, maxHeight: 220 },
  ccTitle: { fontSize: 13, fontWeight: '700', color: colors.ink, marginBottom: 6 },
});
