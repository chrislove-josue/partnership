import React, { useCallback, useEffect, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import { formatSessionDate } from '../api/session';
import type { User } from '../api/types';
import { useAuth } from '../context/AuthContext';
import { countPendingChanges, getMeta, initDatabase, listConflicts } from '../db/database';
import { syncNow } from '../db/sync';
import { ensureStorageAccess, hasPublicStorageAccess, openStorageSettings } from '../db/storage';
import {
  Badge,
  Card,
  colors,
  ErrorView,
  LoadingView,
  PrimaryButton,
  Screen,
  SecondaryButton,
  SectionTitle,
  TextField,
} from '../components/ui';

export function ProfileScreen() {
  const { user, session, refreshUser, logout, apiUrl, canConfigureApiUrl, updateApiUrl } = useAuth();
  const navigation = useNavigation<any>();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [syncInfo, setSyncInfo] = useState({ pending: 0, conflicts: 0, lastSync: '' });
  const [storageOk, setStorageOk] = useState(true);
  const [syncing, setSyncing] = useState(false);

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [handleKc, setHandleKc] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [currentPassword, setCurrentPassword] = useState('');
  const [serverUrl, setServerUrl] = useState(apiUrl);

  useEffect(() => {
    setServerUrl(apiUrl);
  }, [apiUrl]);

  useEffect(() => {
    if (user) {
      setName(user.name);
      setEmail(user.email);
      setPhone(user.phone ?? '');
      setHandleKc(user.handle_kc ?? '');
    }
    setLoading(false);
  }, [user]);

  const loadSyncInfo = useCallback(async () => {
    try {
      await initDatabase();
      await ensureStorageAccess();
      setStorageOk(hasPublicStorageAccess());
      const [pending, conflicts, lastSync] = await Promise.all([
        countPendingChanges(),
        listConflicts(),
        getMeta('last_sync'),
      ]);
      setSyncInfo({ pending, conflicts: conflicts.length, lastSync: lastSync ?? '' });
    } catch {
      setSyncInfo({ pending: 0, conflicts: 0, lastSync: '' });
    }
  }, []);

  useEffect(() => {
    void loadSyncInfo();
  }, [loadSyncInfo]);

  const synchronize = async () => {
    setSyncing(true);
    try {
      const summary = await syncNow();
      await loadSyncInfo();
      Alert.alert(
        'Synchronisation',
        summary.skipped
          ? 'Connectez-vous pour synchroniser les données.'
          : `${summary.pushed} modification(s) envoyée(s), ${summary.pulled} donnée(s) reçue(s). ${summary.conflicts} conflit(s) résolu(s) en faveur du serveur.`,
      );
    } catch (caught) {
      Alert.alert('Synchronisation impossible', getErrorMessage(caught, 'Réessayez plus tard.'));
    } finally {
      setSyncing(false);
    }
  };

  const save = async () => {
    setError('');
    setSuccess('');

    if (password || confirmation) {
      if (!currentPassword) {
        setError('Saisissez d’abord votre mot de passe actuel.');
        return;
      }
      if (!password) {
        setError('Saisissez le nouveau mot de passe.');
        return;
      }
      if (password !== confirmation) {
        setError('La confirmation du mot de passe ne correspond pas.');
        return;
      }
    }

    setSaving(true);
    try {
      const body: Record<string, unknown> = {
        name,
        email,
        phone: phone || null,
        handle_kc: handleKc || null,
      };
      if (password) {
        body.password = password;
        body.password_confirmation = confirmation;
        body.current_password = currentPassword;
      }

      await api<{ user: User }>('/profile', { method: 'PATCH', body });
      if (canConfigureApiUrl && serverUrl !== apiUrl) {
        await updateApiUrl(serverUrl);
      }
      await refreshUser();
      setPassword('');
      setConfirmation('');
      setCurrentPassword('');
      setSuccess('Profil mis à jour.');
    } catch (caught) {
      setError(getErrorMessage(caught, 'Mise à jour impossible.'));
    } finally {
      setSaving(false);
    }
  };

  const loadWebApiUrl = async () => {
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      const response = await api<{ api_url: string }>('/settings/api-url');
      await updateApiUrl(response.api_url);
      setServerUrl(response.api_url);
      setSuccess('Adresse du site web appliquée.');
    } catch (caught) {
      setError(getErrorMessage(caught, 'Adresse du site web indisponible.'));
    } finally {
      setSaving(false);
    }
  };

  const disconnect = () => {
    Alert.alert('Déconnexion', 'Voulez-vous vraiment quitter l’application ?', [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Déconnexion',
        style: 'destructive',
        onPress: () => {
          void logout().catch(() => undefined);
        },
      },
    ]);
  };

  if (loading) {
    return <LoadingView />;
  }

  return (
    <Screen title="Profil" subtitle={user?.email ?? ''} actions={<Badge text={user?.role ?? 'Utilisateur'} />}>
      <Card>
        <SectionTitle>Informations personnelles</SectionTitle>
        <TextField label="Nom complet" value={name} onChangeText={setName} />
        <TextField label="Adresse email" value={email} onChangeText={setEmail} keyboardType="email-address" />
        <TextField label="Téléphone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
        <TextField label="Identifiant KingsChat (handle KC)" value={handleKc} onChangeText={setHandleKc} />
      </Card>

      <Card>
        <SectionTitle>Connexion au serveur</SectionTitle>
        <TextField
          label="Adresse de l'API"
          value={serverUrl}
          onChangeText={setServerUrl}
          placeholder="http://192.168.1.10:8000/api"
        />
        <Text style={styles.help}>
          Utilisez l’adresse de la machine qui héberge Laravel, suivie de /api. Sur un téléphone physique, remplacez localhost par l’IP de cette machine.
        </Text>
      </Card>

      <Card>
        <SectionTitle>Sécurité</SectionTitle>
        <Text style={styles.help}>
          Consultez les connexions et déconnexions enregistrées pour votre compte, sur le site et sur l’application mobile.
        </Text>
        {session ? (
          <Text style={styles.help}>
            Session ouverte le {formatSessionDate(session.connectedAt)}{'\n'}
            Dernière synchronisation : {formatSessionDate(session.lastSyncAt)}
          </Text>
        ) : null}
        <SecondaryButton
          title="Historique de connexion"
          onPress={() => navigation.navigate('HistoriqueConnexion')}
        />
      </Card>

      <Card>
        <SectionTitle>Données hors-ligne</SectionTitle>
        <Text style={styles.help}>
          Modifications en attente : {syncInfo.pending}
        </Text>
        <Text style={styles.help}>
          Conflits résolus en faveur du serveur : {syncInfo.conflicts}
        </Text>
        <Text style={styles.help}>
          Dernière synchronisation : {syncInfo.lastSync ? formatSessionDate(syncInfo.lastSync) : 'jamais'}
        </Text>
        <Text style={styles.help}>
          Sauvegarde conservée dans Documents/FinanceMobile, même après désinstallation.
        </Text>
        <Text style={styles.help}>
          Accès au stockage : {storageOk ? 'autorisé' : 'à autoriser dans les paramètres Android'}
        </Text>
        <View style={styles.syncActions}>
          <SecondaryButton title="Synchroniser maintenant" onPress={() => void synchronize()} disabled={syncing} />
          {storageOk ? null : (
            <SecondaryButton title="Ouvrir les paramètres" onPress={() => void openStorageSettings()} />
          )}
        </View>
      </Card>

      <Card>
        <SectionTitle>Modifier le mot de passe</SectionTitle>
        <TextField label="Mot de passe actuel" value={currentPassword} onChangeText={setCurrentPassword} secureTextEntry />
        <TextField label="Nouveau mot de passe" value={password} onChangeText={setPassword} secureTextEntry />
        <TextField label="Confirmer le nouveau mot de passe" value={confirmation} onChangeText={setConfirmation} secureTextEntry />
        <Text style={styles.help}>Laissez ces champs vides pour conserver votre mot de passe actuel.</Text>
      </Card>

      {error ? <ErrorView message={error} /> : null}
      {success ? <Text style={styles.success}>{success}</Text> : null}

      <View style={styles.actions}>
        <PrimaryButton title="Sauvegarder les modifications" onPress={() => void save()} loading={saving} disabled={!name || !email} />
        <PrimaryButton title="Déconnexion" onPress={disconnect} />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  actions: { gap: 14, marginTop: 4 },
  syncActions: { gap: 14, marginTop: 12 },
  help: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  success: { color: colors.green, fontWeight: '700', marginBottom: 12 },
});
