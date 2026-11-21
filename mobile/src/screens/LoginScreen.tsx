import React, { useEffect, useState } from 'react';
import { Image, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import Ionicons from '@expo/vector-icons/Ionicons';
import { useAuth } from '../context/AuthContext';
import { getApiUrl, getErrorMessage } from '../api/client';
import { AppFooter, colors, PrimaryButton, TextField } from '../components/ui';

export function LoginScreen() {
  const { login, apiUrl, configureApiUrl } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [serverUrl, setServerUrl] = useState(apiUrl);
  const [serverSaved, setServerSaved] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    setServerUrl(apiUrl);
  }, [apiUrl]);

  const applyServer = async () => {
    setError('');
    try {
      await configureApiUrl(serverUrl);
      setServerSaved(true);
    } catch {
      setError('Adresse de l’API invalide. Utilisez une URL HTTPS.');
    }
  };

  const submit = async () => {
    setError('');
    setLoading(true);
    try {
      // L'adresse saisie est appliquée avant la connexion, même si l'utilisateur
      // n'a pas touché le bouton « Utiliser cette adresse ».
      if (serverUrl.trim() !== getApiUrl()) {
        await configureApiUrl(serverUrl);
        setServerSaved(true);
      }
      await login(email.trim(), password);
    } catch (caught) {
      setError(getErrorMessage(caught, 'Connexion impossible. Vérifiez l’adresse de l’API et vos identifiants.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.content}>
        <View style={styles.hero}>
          <Image
            source={require('../../assets/logo.png')}
            style={styles.logo}
            resizeMode="contain"
            accessibilityLabel="Logo FinanceMobile"
          />
        </View>

        <View style={styles.form}>
          <Text style={styles.title}>Connexion</Text>
          <Text style={styles.help}>Utilisez votre email et votre mot de passe pour acceder à votre espace de gestion en toute sécurité.</Text>
          <TextField
            label="Adresse du serveur"
            value={serverUrl}
            onChangeText={(value) => {
              setServerUrl(value);
              setServerSaved(false);
            }}
            placeholder="https://exemple.fr/api"
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
            secureTextEntry={!showPassword}
            trailing={
              <Pressable
                onPress={() => setShowPassword((visible) => !visible)}
                accessibilityRole="button"
                accessibilityLabel={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                hitSlop={8}
                style={styles.passwordVisibilityToggle}
              >
                <Ionicons name={showPassword ? 'eye-off-outline' : 'eye-outline'} size={22} color={colors.blue} />
              </Pressable>
            }
          />
          {error ? <Text style={styles.error}>{error}</Text> : null}
          <PrimaryButton title="Se connecter" onPress={submit} loading={loading} disabled={!email || !password} />
        </View>
      </View>

      <View style={styles.footer}>
        <AppFooter compact />
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.background, paddingHorizontal: 24, paddingTop: 16 },
  content: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  hero: { alignItems: 'center', marginBottom: 20 },
  logo: { width: 280, height: 112 },
  form: {
    width: '100%',
    backgroundColor: colors.surface,
    borderRadius: 20,
    padding: 20,
    borderWidth: 1,
    borderColor: colors.border,
  },
  title: { fontSize: 20, fontWeight: '700', color: colors.navy, marginBottom: 4 },
  help: { fontSize: 13, color: colors.muted, marginBottom: 18 },
  error: { color: colors.red, marginBottom: 12, fontSize: 14 },
  passwordVisibilityToggle: { padding: 4 },
  serverButton: { alignSelf: 'flex-start', marginBottom: 16 },
  serverButtonText: { color: colors.blue, fontWeight: '700', fontSize: 13 },
  footer: { width: '100%', marginTop: 16, marginBottom: 24 },
});
