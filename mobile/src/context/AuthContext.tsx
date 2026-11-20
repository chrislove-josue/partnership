import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import {
  api,
  ApiError,
  clearCache,
  getApiUrl,
  notifyServerLogout,
  setApiUrl,
  setAuthToken,
  setUnauthorizedHandler,
} from '../api/client';
import { clearSession, loadSession, saveSession } from '../api/session';
import type { SessionInfo } from '../api/session';
import { isSuperAdmin, type User } from '../api/types';

const TOKEN_KEY = 'churchmanager_token';
const USER_KEY = 'churchmanager_user';
const API_URL_KEY = 'churchmanager_api_url';
const ADMIN_DEVICE_KEY = 'churchmanager_api_admin';

interface AuthContextValue {
  user: User | null;
  session: SessionInfo | null;
  loading: boolean;
  apiUrl: string;
  canConfigureApiUrl: boolean;
  updateApiUrl: (url: string) => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [session, setSession] = useState<SessionInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [apiUrl, setApiUrlState] = useState(getApiUrl());
  const [canConfigureApiUrl, setCanConfigureApiUrl] = useState(false);

  const updateApiUrl = useCallback(async (url: string) => {
    if (!canConfigureApiUrl) {
      throw new Error('Seul le super administrateur peut modifier l’adresse de l’API.');
    }

    const normalized = url.trim().replace(/\/+$/, '');
    await SecureStore.setItemAsync(API_URL_KEY, normalized);
    setApiUrl(normalized);
    setApiUrlState(normalized);
  }, [canConfigureApiUrl]);

  const forget = useCallback(async () => {
    clearCache();
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await SecureStore.deleteItemAsync(USER_KEY);
    await clearSession();
    setAuthToken(null);
    setUser(null);
    setSession(null);
  }, []);

  useEffect(() => {
    let active = true;

    (async () => {
      try {
        const storedUrl = await SecureStore.getItemAsync(API_URL_KEY);
        if (storedUrl) {
          setApiUrl(storedUrl);
          setApiUrlState(storedUrl);
        }

        const adminDevice = await SecureStore.getItemAsync(ADMIN_DEVICE_KEY);
        setCanConfigureApiUrl(adminDevice === '1');

        const token = await SecureStore.getItemAsync(TOKEN_KEY);
        if (!token) {
          return;
        }

        setAuthToken(token);
        const saved = await SecureStore.getItemAsync(USER_KEY);
        if (saved) {
          setUser(JSON.parse(saved) as User);
        }
        const storedSession = await loadSession();
        if (storedSession) {
          setSession(storedSession);
        }

        const response = await api<{ user: User }>('/me');
        if (!active) {
          return;
        }

        setUser(response.user);
        await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));
        const synced: SessionInfo = {
          connectedAt: storedSession?.connectedAt ?? new Date().toISOString(),
          lastSyncAt: new Date().toISOString(),
        };
        await saveSession(synced);
        setSession(synced);
      } catch (error) {
        // Hors-ligne, on conserve le jeton et l'utilisateur mémorisés : le cache
        // local reste consultable. Seul un 401 signifie une session terminée.
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
    // L'appel serveur démarre avec le jeton encore présent, mais la session
    // locale est effacée immédiatement : l'utilisateur revient à la connexion
    // même si le réseau est lent ou indisponible.
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
    await SecureStore.setItemAsync(ADMIN_DEVICE_KEY, allowed ? '1' : '0');
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
      const allowed = isSuperAdmin(response.user);
      await SecureStore.setItemAsync(ADMIN_DEVICE_KEY, allowed ? '1' : '0');
      setCanConfigureApiUrl(allowed);
      await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));
      const synced: SessionInfo = {
        connectedAt: session?.connectedAt ?? new Date().toISOString(),
        lastSyncAt: new Date().toISOString(),
      };
      await saveSession(synced);
      setSession(synced);
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        await logout();
      }
    }
  }, [logout, session]);

  const value = useMemo(
    () => ({ user, session, loading, apiUrl, canConfigureApiUrl, updateApiUrl, login, logout, refreshUser }),
    [user, session, loading, apiUrl, canConfigureApiUrl, updateApiUrl, login, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth doit être utilisé dans AuthProvider.');
  }

  return context;
}
