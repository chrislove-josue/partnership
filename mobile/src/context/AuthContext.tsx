import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { api, ApiError, getApiUrl, notifyServerLogout, setApiUrl, setAuthToken, setUnauthorizedHandler } from '../api/client';
import { clearSession, loadSession, saveSession } from '../api/session';
import type { SessionInfo } from '../api/session';
import { isSuperAdmin, type User } from '../api/types';

const TOKEN_KEY = 'churchmanager_token';
const USER_KEY = 'churchmanager_user';
const API_URL_KEY = 'churchmanager_api_url';
// Ancien drapeau local : le droit de configuration est recalculé depuis /me.
const LEGACY_ADMIN_DEVICE_KEY = 'churchmanager_api_admin';

interface AuthContextValue {
  user: User | null;
  session: SessionInfo | null;
  loading: boolean;
  apiUrl: string;
  canConfigureApiUrl: boolean;
  /** Adresse saisie sur l'écran de connexion, avant toute authentification. */
  configureApiUrl: (url: string) => Promise<void>;
  updateApiUrl: (url: string) => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function isSameUser(first: User | null, second: User | null): boolean {
  return first !== null && second !== null && first.id === second.id;
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [session, setSession] = useState<SessionInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [apiUrl, setApiUrlState] = useState(getApiUrl());
  const [canConfigureApiUrl, setCanConfigureApiUrl] = useState(false);
  const userRef = useRef<User | null>(null);

  useEffect(() => {
    userRef.current = user;
  }, [user]);

  // L'écran de connexion doit pouvoir choisir le serveur avant d'être authentifié.
  const configureApiUrl = useCallback(async (url: string) => {
    const normalized = setApiUrl(url);
    await SecureStore.setItemAsync(API_URL_KEY, normalized);
    setApiUrlState(normalized);
  }, []);

  const updateApiUrl = useCallback(async (url: string) => {
    if (!canConfigureApiUrl) {
      throw new Error('Seul le super administrateur peut modifier l’adresse de l’API.');
    }
    await configureApiUrl(url);
  }, [canConfigureApiUrl, configureApiUrl]);

  const forget = useCallback(async () => {
    // Seuls les identifiants sont conservés sur l'appareil : aucune donnée métier.
    await Promise.allSettled([
      SecureStore.deleteItemAsync(TOKEN_KEY),
      SecureStore.deleteItemAsync(USER_KEY),
      SecureStore.deleteItemAsync(LEGACY_ADMIN_DEVICE_KEY),
      clearSession(),
    ]);
    setAuthToken(null);
    setUser(null);
    setSession(null);
    setCanConfigureApiUrl(false);
  }, []);

  useEffect(() => {
    let active = true;

    (async () => {
      try {
        const storedUrl = await SecureStore.getItemAsync(API_URL_KEY);
        if (storedUrl) {
          try {
            setApiUrlState(setApiUrl(storedUrl));
          } catch {
            await SecureStore.deleteItemAsync(API_URL_KEY);
            setApiUrlState(getApiUrl());
          }
        }

        const token = await SecureStore.getItemAsync(TOKEN_KEY);
        if (!token) {
          return;
        }
        setAuthToken(token);

        const storedSession = await loadSession();
        if (storedSession) {
          setSession(storedSession);
        }

        // L'utilisateur est TOUJOURS chargé depuis la base (endpoint /me).
        const response = await api<{ user: User }>('/me');
        if (!active) {
          return;
        }
        setUser(response.user);
        setCanConfigureApiUrl(isSuperAdmin(response.user));
        await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));

        const current: SessionInfo = {
          connectedAt: storedSession?.connectedAt ?? new Date().toISOString(),
          lastSyncAt: new Date().toISOString(),
        };
        await saveSession(current);
        setSession(current);
      } catch (error) {
        // Une session expirée (401) est la seule raison de déconnecter.
        // En cas de panne réseau, l'écran de connexion reste affiché :
        // aucune donnée locale n'est utilisée.
        if (error instanceof ApiError && error.status === 401) {
          await forget();
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    })();

    return () => {
      active = false;
    };
  }, [forget]);

  const logout = useCallback(async () => {
    const serverLogout = notifyServerLogout();
    await forget();
    await serverLogout;
  }, [forget]);

  useEffect(() => {
    setUnauthorizedHandler(() => {
      void logout();
    });
    return () => setUnauthorizedHandler(null);
  }, [logout]);

  const login = useCallback(async (email: string, password: string) => {
    const response = await api<{ token: string; user: User }>('/login', {
      method: 'POST',
      body: { email, password },
      authenticated: false,
    });

    const allowed = isSuperAdmin(response.user);
    await SecureStore.setItemAsync(TOKEN_KEY, response.token);
    await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));
    setCanConfigureApiUrl(allowed);
    const now = new Date().toISOString();
    const opened: SessionInfo = { connectedAt: now, lastSyncAt: now };
    await saveSession(opened);
    setAuthToken(response.token);
    setUser(response.user);
    setSession(opened);
  }, []);

  const refreshUser = useCallback(async () => {
    try {
      const response = await api<{ user: User }>('/me');
      setUser(response.user);
      setCanConfigureApiUrl(isSuperAdmin(response.user));
      await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));
      const refreshed: SessionInfo = {
        connectedAt: session?.connectedAt ?? new Date().toISOString(),
        lastSyncAt: new Date().toISOString(),
      };
      await saveSession(refreshed);
      setSession(refreshed);
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        await logout();
      }
    }
  }, [logout, session]);

  const value = useMemo(
    () => ({
      user,
      session,
      loading,
      apiUrl,
      canConfigureApiUrl,
      configureApiUrl,
      updateApiUrl,
      login,
      logout,
      refreshUser,
    }),
    [user, session, loading, apiUrl, canConfigureApiUrl, configureApiUrl, updateApiUrl, login, logout, refreshUser]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth doit être utilisé dans AuthProvider.');
  }
  return context;
}
