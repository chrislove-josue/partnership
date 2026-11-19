import React, { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Linking, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '../context/AuthContext';
import { ApiError } from '../api/client';
import { colors, PrimaryButton, TextField } from '../components/ui';

export function LoginScreen() {
  const { login, apiUrl, updateApiUrl } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [serverUrl, setServerUrl] = useState(apiUrl);
  const [serverSaved, setServerSaved] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setServerUrl(apiUrl);
  }, [apiUrl]);

  const applyServer = async () => {
    await updateApiUrl(serverUrl);
    setServerSaved(true);
  };

  const submit = async () => {
    setError('');
    setLoading(true);
    try {
      await login(email.trim(), password);
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'Connexion impossible.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.hero}>
        <View style={styles.logo}>
          <Text style={styles.logoText}>CE</Text>
        </View>
        <Text style={styles.appName}>ChurchManager</Text>
        <Text style={styles.tagline}>Gestion Financière — Christ Embassy Benin Republic</Text>
      </View>

      <View style={styles.form}>
        <Text style={styles.title}>Connexion</Text>
        <Text style={styles.help}>Utilisez votre email et votre mot de passe pour vous connecter.</Text>
        <TextField
          label="Adresse du serveur"
          value={serverUrl}
          onChangeText={(value) => {
            setServerUrl(value);
            setServerSaved(false);
          }}
          placeholder="http://192.168.1.10:8000/api"
        />
        <Pressable style={styles.serverButton} onPress={() => void applyServer()}>
          <Text style={styles.serverButtonText}>
            {serverSaved ? 'Adresse enregistrée ✓' : 'Utiliser cette adresse'}
          </Text>
        </Pressable>
        <TextField
          label="Adresse email"
          value={email}
          onChangeText={setEmail}
          placeholder="nestoratchougo@exemple.com"
          keyboardType="email-address"
        />
        <TextField
          label="Mot de passe"
          value={password}
          onChangeText={setPassword}
          placeholder="Mot de passe"
          secureTextEntry
        />
        {error ? <Text style={styles.error}>{error}</Text> : null}
        <PrimaryButton title="Se connecter" onPress={submit} loading={loading} disabled={!email || !password} />
        <Pressable
          style={styles.assistanceLink}
          onPress={() => void Linking.openURL('https://wa.me/22998123353')}
        >
          <Text style={styles.footer}>Pour toute assistance : +229 98 12 33 53</Text>
        </Pressable>
        <Text style={styles.credit}>Conçu et Développé par Nestor ATCHOUGO</Text>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, justifyContent: 'center', padding: 24 },
  hero: { alignItems: 'center', marginBottom: 30 },
  logo: {
    width: 76,
    height: 76,
    borderRadius: 22,
    backgroundColor: colors.navy,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  logoText: { color: colors.gold, fontSize: 28, fontWeight: '800' },
  appName: { fontSize: 24, fontWeight: '800', color: colors.navy },
  tagline: { fontSize: 13, color: colors.muted, marginTop: 4, textAlign: 'center' },
  form: {
    backgroundColor: colors.surface,
    borderRadius: 20,
    padding: 20,
    borderWidth: 1,
    borderColor: colors.border,
  },
  title: { fontSize: 20, fontWeight: '700', color: colors.navy, marginBottom: 4 },
  help: { fontSize: 13, color: colors.muted, marginBottom: 18 },
  error: { color: colors.red, marginBottom: 12, fontSize: 14 },
  serverButton: { alignSelf: 'flex-start', marginBottom: 16 },
  serverButtonText: { color: colors.blue, fontWeight: '700', fontSize: 13 },
  assistanceLink: { alignItems: 'center', paddingVertical: 6 },
  footer: { textAlign: 'center', color: colors.muted, fontSize: 12, marginTop: 18 },
  credit: { textAlign: 'center', color: colors.muted, fontSize: 11, marginTop: 10 },
});
