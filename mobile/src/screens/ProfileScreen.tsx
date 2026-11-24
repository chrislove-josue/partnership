import React, { useCallback, useEffect, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { api, getErrorMessage, normalizeApiUrl } from '../api/client';
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
  const { user, session, refreshUser, logout, apiUrl, canConfigureApiUrl, updateApiUrl } = useAuth();
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
  const [proposedApiUrl, setProposedApiUrl] = useState('');

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
    setProposedApiUrl('');
    try {
      const response = await api<{ api_url: string }>('/settings/api-url');
      // Le serveur n'est pas autorisé à changer l'adresse utilisée par l'app :
      // on la propose seulement à l'administrateur, qui doit la confirmer.
      const proposal = normalizeApiUrl(response.api_url ?? '');
      if (!proposal) {
        throw new Error('Adresse vide.');
      }
      setProposedApiUrl(proposal);
      setSuccess('Adresse proposée par le site web : vérifiez-la avant de l’appliquer.');
    } catch {
      setError('L’adresse proposée par le site web est invalide.');
    } finally {
      setSaving(false);
    }
  };

  const applyProposedApiUrl = () => {
    Alert.alert(
      'Confirmer l’adresse de l’API',
      `Remplacer l’adresse utilisée par ${proposedApiUrl} ?`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Appliquer',
          style: 'destructive',
          onPress: () => {
            void updateApiUrl(proposedApiUrl)
              .then(() => {
                setServerUrl(proposedApiUrl);
                setProposedApiUrl('');
                setSuccess('Adresse de l’API mise à jour.');
              })
              .catch((caught: unknown) => {
                setError(getErrorMessage(caught, 'Adresse de l’API invalide.'));
              });
          },
        },
      ],
    );
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
        {canConfigureApiUrl ? (
          <>
            <TextField
              label="Adresse de l'API (super administrateur)"
              value={serverUrl}
              onChangeText={setServerUrl}
              placeholder="https://exemple.fr/api"
            />
            <Text style={styles.help}>
              Utilisez une URL HTTPS. Le HTTP n’est accepté en développement que pour localhost et l’émulateur.
            </Text>
            <SecondaryButton
              title="Voir l’adresse configurée sur le site web"
              onPress={() => void loadWebApiUrl()}
              disabled={saving}
            />
            {proposedApiUrl ? (
              <>
                <Text style={styles.help}>Adresse proposée : {proposedApiUrl}</Text>
                <SecondaryButton
                  title="Appliquer l’adresse proposée"
                  onPress={applyProposedApiUrl}
                  disabled={saving}
                />
              </>
            ) : null}
          </>
        ) : (
          <Text style={styles.help}>
            Adresse configurée sur cet appareil : {apiUrl}. Seul le super administrateur peut la modifier.
          </Text>
        )}
      </Card>

      <Card>
        <SectionTitle>Sécurité</SectionTitle>
        <Text style={styles.help}>
          Consultez les connexions et déconnexions enregistrées pour votre compte, sur le site et sur l’application mobile.
        </Text>
        {session ? (
          <Text style={styles.help}>
            Session ouverte le {formatSessionDate(session.connectedAt)}{'\n'}
          </Text>
        ) : null}
        <SecondaryButton
          title="Historique de connexion"
          onPress={() => navigation.navigate('HistoriqueConnexion')}
        />
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
