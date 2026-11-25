import React, { useCallback, useEffect, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { api, apiDownload, getErrorMessage } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { FilterCard } from '../components/FilterCard';
import { defaultChurchId } from '../utils/church';
import { filterBy } from '../utils/search';
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

export function ReportsScreen() {
  const { user } = useAuth();
  const [loadingChurches, setLoadingChurches] = useState(true);
  const [churches, setChurches] = useState<Church[]>([]);
  const [churchId, setChurchId] = useState('');
  const [report, setReport] = useState<PartnershipReport | null>(null);
  const [reportSearch, setReportSearch] = useState('');
  const [reportSection, setReportSection] = useState('');
  const [categoryGroup, setCategoryGroup] = useState('');
  const now = new Date();
  const [periodType, setPeriodType] = useState('month');
  const [month, setMonth] = useState(String(now.getMonth() + 1));
  const [quarter, setQuarter] = useState(String(Math.floor(now.getMonth() / 3) + 1));
  const [year, setYear] = useState(String(now.getFullYear()));
  const [preview, setPreview] = useState<RecipientsPreview | null>(null);
  const [selectedCc, setSelectedCc] = useState<string[]>([]);
  const [confirming, setConfirming] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const response = await api<{ data: Church[] }>('/churches');
        setChurches(response.data);
        setChurchId((current) => current || defaultChurchId(response.data, user?.assigned_church_id));
      } catch (caught) {
        setError(getErrorMessage(caught, 'Impossible de charger les églises.'));
      } finally {
        setLoadingChurches(false);
      }
    })();
  }, [user?.assigned_church_id]);

  const query = useCallback(() => {
    const params = new URLSearchParams({
      church_id: churchId,
      type: periodType,
      year,
    });
    if (periodType === 'month') params.set('month', month);
    if (periodType === 'quarter') params.set('quarter', quarter);
    if (categoryGroup) params.set('category_group', categoryGroup);
    return params.toString();
  }, [churchId, periodType, month, quarter, year, categoryGroup]);

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
        category_group: categoryGroup || null,
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
      const suffix = periodType === 'month' ? month : periodType === 'quarter' ? `T${quarter}` : '';
      const fileName = `rapport-${churchId}-${periodType}-${year}${suffix ? `-${suffix}` : ''}.xlsx`;
      await apiDownload(`/reports/partnership/export?${query()}`, fileName);
      Alert.alert('Rapport téléchargé', 'Le fichier est prêt.');
    } catch (caught) {
      setError(getErrorMessage(caught, 'Export impossible.'));
    } finally {
      setLoading(false);
    }
  };

  const churchOptions = churches.map((church) => ({ label: church.name, value: String(church.id) }));

  const filteredReport = report ? {
    categories: filterBy(report.categories, reportSearch, [(item) => item.name, (item) => item.amount]),
    partners: filterBy(report.partners, reportSearch, [(item) => item.name, (item) => item.amount, (item) => item.cellule]),
    donations: filterBy(report.donations, reportSearch, [
      (item) => item.partner,
      (item) => item.category,
      (item) => item.amount,
      (item) => item.date,
    ]),
  } : null;

  const showSection = (key: string) => !reportSection || reportSection === key;
  const resetReportFilters = () => {
    setReportSearch('');
    setReportSection('');
    setCategoryGroup('');
    setPeriodType('month');
    setMonth(String(now.getMonth() + 1));
    setQuarter(String(Math.floor(now.getMonth() / 3) + 1));
    setYear(String(now.getFullYear()));
  };

  return (
    <Screen title="Rapport de partenariat" subtitle="Générez, exportez et envoyez à la hiérarchie">
      <Card>
        {loadingChurches ? <LoadingView /> : null}
        <SelectField label="Église" value={churchId} options={churchOptions} onChange={setChurchId} searchable />
        <SelectField label="Période" value={periodType} options={PERIODS} onChange={setPeriodType} />
        {periodType === 'month' ? (
          <SelectField label="Mois" value={month} options={MONTHS} onChange={setMonth} />
        ) : null}
        {periodType === 'quarter' ? (
          <SelectField label="Trimestre" value={quarter} options={QUARTERS} onChange={setQuarter} />
        ) : null}
        <SelectField label="Année" value={year} options={[
          { label: String(Number(year) - 1), value: String(Number(year) - 1) },
          { label: String(year), value: year },
          { label: String(Number(year) + 1), value: String(Number(year) + 1) },
        ]} onChange={setYear} />
        <SelectField
          label="Famille de catégorie"
          value={categoryGroup}
          options={[
            { label: 'Toutes les familles', value: '' },
            { label: 'Finances de l\'église', value: 'church_finances' },
            { label: 'Partenariat du ministère', value: 'ministry_partnership' },
            { label: 'Partenariat local', value: 'local_partnership' },
          ]}
          onChange={setCategoryGroup}
        />
        <View style={styles.buttonRow}>
          <PrimaryButton title="Générer" onPress={() => void generate()} disabled={!churchId || loading} />
          <PrimaryButton title="Exporter" onPress={() => void exportReport()} disabled={!churchId || loading} />
        </View>
      </Card>

      {error ? <ErrorView message={error} /> : null}
      {loading && !report ? <LoadingView /> : null}
      {!loading && !report && !error ? <EmptyState message="Choisissez une église puis générez le rapport." /> : null}

      {report && filteredReport ? (
        <>
          <FilterCard
            query={reportSearch}
            onQueryChange={setReportSearch}
            placeholder="Partenaire, catégorie, montant, date"
            onReset={resetReportFilters}
            active={Boolean(reportSearch || reportSection)}
          >
            <SelectField
              label="Section"
              value={reportSection}
              options={[
                { label: 'Toutes les sections', value: '' },
                { label: 'Par catégorie', value: 'categories' },
                { label: 'Partenaires', value: 'partners' },
                { label: 'Détail des dons', value: 'donations' },
              ]}
              onChange={setReportSection}
            />
          </FilterCard>

          <Card>
            <SectionTitle>{report.church.name}</SectionTitle>
            <Badge text={report.period.label} />
            {report.church.hierarchyPath ? (
              <Text style={styles.meta}>{report.church.hierarchyPath}</Text>
            ) : null}
            <Text style={styles.total}>{formatMoney(report.totals.amount)}</Text>
            <Text style={styles.meta}>
              {report.totals.donationCount} don(s) · {report.totals.partnersWithDonation} partenaire(s) avec don
            </Text>
            <PrimaryButton title="Préparer l’envoi" onPress={() => void prepareSend()} loading={loading} disabled={loading} />
          </Card>

          {showSection('categories') ? (
            <Card>
              <SectionTitle>Par catégorie</SectionTitle>
              {filteredReport.categories.length === 0 ? <Text style={styles.meta}>Aucune catégorie pour ces filtres.</Text> : null}
              {filteredReport.categories.map((category) => (
                <View key={category.name} style={styles.listRow}>
                  <View style={[styles.color, { backgroundColor: category.color }]} />
                  <View style={{ flex: 1 }}>
                    <Text style={styles.listLabel}>{category.name}</Text>
                    {category.group_label ? <Text style={styles.meta}>{category.group_label}</Text> : null}
                  </View>
                  <Text style={styles.listValue}>{formatMoney(category.amount)}</Text>
                </View>
              ))}
            </Card>
          ) : null}

          {showSection('partners') ? (
            <Card>
              <SectionTitle>Partenaires</SectionTitle>
              {filteredReport.partners.length === 0 ? <Text style={styles.meta}>Aucun partenaire pour ces filtres.</Text> : null}
              {filteredReport.partners.map((partner) => (
                <View key={partner.name} style={styles.listRow}>
                  <Text style={styles.listLabel}>{partner.name}</Text>
                  <Text style={styles.listValue}>{formatMoney(partner.amount)}</Text>
                </View>
              ))}
            </Card>
          ) : null}

          {showSection('donations') ? (
            <Card>
              <SectionTitle>Détail des dons</SectionTitle>
              {filteredReport.donations.length === 0 ? <Text style={styles.meta}>Aucun don pour ces filtres.</Text> : null}
              <ScrollView horizontal>
                <View>
                  {filteredReport.donations.map((donation) => (
                    <View key={`${donation.date}-${donation.partner}-${donation.amount}`} style={styles.listRow}>
                      <Text style={styles.listLabel}>{donation.partner} · {donation.category}</Text>
                      <Text style={styles.listValue}>{formatMoney(donation.amount)}</Text>
                      <Text style={styles.meta}>{formatDate(donation.date)}</Text>
                    </View>
                  ))}
                </View>
              </ScrollView>
            </Card>
          ) : null}
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
  buttonRow: { flexDirection: 'row', gap: 14, marginTop: 6 },
  total: { fontSize: 26, fontWeight: '800', color: colors.blue, marginTop: 12 },
  meta: { fontSize: 13, color: colors.muted, marginTop: 5 },
  listRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.border },
  color: { width: 10, height: 10, borderRadius: 5 },
  listLabel: { flex: 1, color: colors.ink, fontSize: 14 },
  listValue: { fontWeight: '700', color: colors.blue, fontSize: 14 },
  ccBox: { marginVertical: 8, maxHeight: 220 },
  ccTitle: { fontSize: 13, fontWeight: '700', color: colors.ink, marginBottom: 6 },
});
