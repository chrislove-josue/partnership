import React, { useEffect, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { api, getErrorMessage } from '../api/client';
import { formatSessionDate } from '../api/session';
import type { User } from '../api/types';
import { useAuth } from '../context/AuthContext';
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
  const { user, session, refreshUser, logout, apiUrl, updateApiUrl } = useAuth();
  const navigation = useNavigation<any>();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

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

  const save = async () => {
    setSaving(true);
    setError('');
    setSuccess('');
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
      await updateApiUrl(serverUrl);
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

  const disconnect = () => {
    Alert.alert('Déconnexion', 'Voulez-vous vraiment quitter l’application ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Déconnexion', style: 'destructive', onPress: () => void logout() },
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
        <SectionTitle>Modifier le mot de passe</SectionTitle>
        <TextField label="Nouveau mot de passe" value={password} onChangeText={setPassword} secureTextEntry />
        <TextField label="Confirmer le mot de passe" value={confirmation} onChangeText={setConfirmation} secureTextEntry />
        <TextField label="Mot de passe actuel" value={currentPassword} onChangeText={setCurrentPassword} secureTextEntry />
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
  help: { color: colors.muted, fontSize: 12, lineHeight: 18 },
  success: { color: colors.green, fontWeight: '700', marginBottom: 12 },
});
