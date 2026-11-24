import React, { useCallback, useEffect, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { api, getErrorMessage, invalidateCache } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { defaultChurchId } from '../utils/church';
import { filterBy } from '../utils/search';
import type { Category, Church, Donation, Partner } from '../api/types';
import { ImportExportCard } from '../components/ImportExportCard';
import {
  Badge,
  Card,
  colors,
  ConfirmModal,
  DateField,
  EmptyState,
  ErrorView,
  formatDate,
  formatMoney,
  LoadingView,
  PrimaryButton,
  Screen,
  SecondaryButton,
  SectionTitle,
  SelectField,
  TextField,
} from '../components/ui';

export function DonationsScreen() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [donations, setDonations] = useState<Donation[]>([]);
  const [churches, setChurches] = useState<Church[]>([]);
  const [partners, setPartners] = useState<Partner[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [search, setSearch] = useState('');
  const [churchFilter, setChurchFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [partnerFilter, setPartnerFilter] = useState('');
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');
  const [confirmDelete, setConfirmDelete] = useState<Donation | null>(null);

  const [amount, setAmount] = useState('');
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [churchId, setChurchId] = useState('');
  const [partnerId, setPartnerId] = useState('');
  const [categoryId, setCategoryId] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const query = new URLSearchParams();
      if (churchFilter) query.set('church_id', churchFilter);
      if (categoryFilter) query.set('category_id', categoryFilter);
      if (partnerFilter) query.set('partner_id', partnerFilter);
      const donationPath = `/donations${query.toString() ? `?${query}` : ''}`;

      const [donationData, churchData, partnerData, categoryData] = await Promise.all([
        api<{ data: Donation[] }>(donationPath, { cacheKey: `donations:${query}` }),
        api<{ data: Church[] }>('/churches', { cacheKey: 'churches' }),
        api<{ data: Partner[] }>('/partners', { cacheKey: 'partners' }),
        api<{ data: Category[] }>('/categories', { cacheKey: 'categories' }),
      ]);
      setDonations(donationData.data);
      setChurches(churchData.data);
      setPartners(partnerData.data);
      setCategories(categoryData.data);
      setChurchId((current) => current || defaultChurchId(churchData.data, user?.assigned_church_id));
      setCategoryId((current) => current || categoryData.data[0]?.id.toString() || '');
    } catch (caught) {
      setError(getErrorMessage(caught, 'Impossible de charger les dons.'));
    } finally {
      setLoading(false);
    }
  }, [churchFilter, categoryFilter, partnerFilter, user?.assigned_church_id]);

  useEffect(() => {
    const timer = setTimeout(() => void load(), 250);
    return () => clearTimeout(timer);
  }, [load]);

  const resetFilters = () => {
    setSearch('');
    setChurchFilter('');
    setCategoryFilter('');
    setPartnerFilter('');
  };

  useFocusEffect(useCallback(() => {
    void load();
  }, [load]));

  const churchOptions = churches.map((church) => ({ label: church.name, value: String(church.id) }));
  const partnerOptions = partners.map((partner) => ({
    label: partner.full_name ?? `${partner.first_name} ${partner.last_name}`,
    value: String(partner.id),
  }));
  // Tous les partenaires sont disponibles, même s'ils appartiennent à une autre église.
  const formPartnerOptions = partnerOptions;
  const categoryOptions = categories.map((category) => ({ label: category.name, value: String(category.id) }));
  const visibleDonations = filterBy(donations, search, [
    (donation) => donation.partner,
    (donation) => donation.church,
    (donation) => donation.category,
    (donation) => donation.amount,
    (donation) => donation.date,
  ]);

  const openForm = () => {
    setFormError('');
    setAmount('');
    setPartnerId(formPartnerOptions[0]?.value ?? '');
    setFormOpen(true);
  };

  const remove = async () => {
    if (!confirmDelete) {
      return;
    }

    try {
      await api(`/donations/${confirmDelete.id}`, { method: 'DELETE' });
      invalidateCache();
      setConfirmDelete(null);
      await load();
    } catch (caught) {
      setError(getErrorMessage(caught, 'Suppression impossible.'));
      setConfirmDelete(null);
    }
  };

  const save = async () => {
    setFormError('');
    const parsedAmount = Number(amount);
    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
      setFormError('Le montant doit être un nombre positif.');
      return;
    }

    setSaving(true);
    try {
      await api('/donations', {
        method: 'POST',
        body: {
          amount: parsedAmount,
          date,
          church_id: Number(churchId),
          partner_id: Number(partnerId),
          category_id: Number(categoryId),
        },
      });
      invalidateCache();
      setFormOpen(false);
      await load();
    } catch (caught) {
      setFormError(getErrorMessage(caught, 'Enregistrement impossible.'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Screen
      title="Dons"
      subtitle={`${visibleDonations.length} don(s)`}
      actions={<PrimaryButton title="Nouveau don" onPress={openForm} />}
    >
      {loading ? <LoadingView /> : null}
      {!loading && error ? <ErrorView message={error} onRetry={() => void load()} /> : null}

      {!loading && !error ? (
        <>
          <ImportExportCard
            title="Dons Excel"
            help="Colonnes : churchName (ou church), donationAmount, donationDate, donationCategoryName, puis email/phone/handleKC d’un partenaire existant ou firstName + lastName pour en créer un."
            importPath="/donations/import"
            templatePath="/donations/template"
            templateFileName="modele_dons.xlsx"
            onImported={() => void load()}
          />

          <Card>
            <SectionTitle>Recherche et filtres</SectionTitle>
            <TextField
              label="Rechercher"
              value={search}
              onChangeText={setSearch}
              placeholder="Partenaire, église, catégorie, montant"
            />
            <SelectField
              label="Église"
              value={churchFilter}
              options={[{ label: 'Toutes les églises', value: '' }, ...churchOptions]}
              onChange={setChurchFilter}
              searchable
            />
            <SelectField
              label="Catégorie"
              value={categoryFilter}
              options={[{ label: 'Toutes les catégories', value: '' }, ...categoryOptions]}
              onChange={setCategoryFilter}
              searchable
            />
            <SelectField
              label="Partenaire"
              value={partnerFilter}
              options={[{ label: 'Tous les partenaires', value: '' }, ...partnerOptions]}
              onChange={setPartnerFilter}
              searchable
            />
            <SecondaryButton title="Réinitialiser les filtres" onPress={resetFilters} />
          </Card>

          {visibleDonations.length === 0 ? <EmptyState message="Aucun don pour ces filtres." /> : null}

          {visibleDonations.map((donation) => (
            <Card key={donation.id}>
              <View style={styles.row}>
                <Text style={styles.amount}>{formatMoney(donation.amount)}</Text>
                <Badge text={donation.category ?? '—'} color={donation.color ?? colors.blue} />
              </View>
              <Text style={styles.partner}>{donation.partner ?? 'Partenaire inconnu'}</Text>
              <Text style={styles.meta}>
                {donation.church ?? '—'}
                {donation.worship_church && donation.worship_church !== donation.church
                  ? ` · culte : ${donation.worship_church}`
                  : ''}
                {' · '}
                {formatDate(donation.date)}
              </Text>
              <Pressable style={styles.delete} onPress={() => setConfirmDelete(donation)}>
                <Text style={styles.deleteText}>Supprimer</Text>
              </Pressable>
            </Card>
          ))}
        </>
      ) : null}

      <Modal visible={formOpen} animationType="slide" onRequestClose={() => setFormOpen(false)}>
        <Screen
          title="Nouveau don"
          subtitle="Renseignez les informations du don"
          actions={<Pressable onPress={() => setFormOpen(false)}><Text style={styles.close}>Fermer</Text></Pressable>}
        >
          <Card>
            <TextField label="Montant (FCFA)" value={amount} onChangeText={setAmount} keyboardType="numeric" placeholder="Ex. 5000" />
            <DateField label="Date du don" value={date} onChange={setDate} />
            <SelectField
              label="Église"
              value={churchId}
              options={churchOptions}
              onChange={(value) => {
                setChurchId(value);
                setPartnerId('');
              }}
              searchable
            />
            <SelectField
              label="Partenaire"
              value={partnerId}
              options={formPartnerOptions}
              onChange={setPartnerId}
              placeholder="Choisir un partenaire"
              searchable
            />
            <SelectField label="Catégorie" value={categoryId} options={categoryOptions} onChange={setCategoryId} />
            {formError ? <Text style={styles.error}>{formError}</Text> : null}
            <PrimaryButton
              title="Enregistrer le don"
              onPress={() => void save()}
              loading={saving}
              disabled={!amount || !churchId || !partnerId || !categoryId}
            />
          </Card>
        </Screen>
      </Modal>

      <ConfirmModal
        visible={confirmDelete !== null}
        title="Supprimer ce don ?"
        message="Cette action est réservée aux super administrateurs."
        confirmLabel="Supprimer"
        onCancel={() => setConfirmDelete(null)}
        onConfirm={() => void remove()}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  amount: { fontSize: 20, fontWeight: '800', color: colors.blue },
  partner: { fontSize: 15, fontWeight: '600', color: colors.ink, marginTop: 8 },
  meta: { fontSize: 12, color: colors.muted, marginTop: 4 },
  close: { color: colors.blue, fontWeight: '700' },
  error: { color: colors.red, marginBottom: 12 },
  delete: { alignSelf: 'flex-start', marginTop: 10 },
  deleteText: { color: colors.red, fontSize: 13, fontWeight: '600' },
});
