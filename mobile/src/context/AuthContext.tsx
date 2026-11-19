import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { api, ApiError, setAuthToken } from '../api/client';
import type { User } from '../api/types';

const TOKEN_KEY = 'churchmanager_token';
const USER_KEY = 'churchmanager_user';

interface AuthContextValue {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;

    (async () => {
      try {
        const token = await SecureStore.getItemAsync(TOKEN_KEY);
        if (!token) {
          return;
        }

        setAuthToken(token);
        const saved = await SecureStore.getItemAsync(USER_KEY);
        if (saved) {
          setUser(JSON.parse(saved) as User);
        }

        const response = await api<{ user: User }>('/me');
        if (active) {
          setUser(response.user);
          await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));
        }
      } catch {
        await SecureStore.deleteItemAsync(TOKEN_KEY);
        await SecureStore.deleteItemAsync(USER_KEY);
        setAuthToken(null);
        if (active) {
          setUser(null);
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
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const response = await api<{ token: string; user: User }>('/login', {
      method: 'POST',
      body: { email, password },
      authenticated: false,
    });

    await SecureStore.setItemAsync(TOKEN_KEY, response.token);
    await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));
    setAuthToken(response.token);
    setUser(response.user);
  }, []);

  const logout = useCallback(async () => {
    try {
      await api('/logout', { method: 'POST' });
    } catch (error) {
      if (!(error instanceof ApiError)) {
        throw error;
      }
    } finally {
      await SecureStore.deleteItemAsync(TOKEN_KEY);
      await SecureStore.deleteItemAsync(USER_KEY);
      setAuthToken(null);
      setUser(null);
    }
  }, []);

  const refreshUser = useCallback(async () => {
    try {
      const response = await api<{ user: User }>('/me');
      setUser(response.user);
      await SecureStore.setItemAsync(USER_KEY, JSON.stringify(response.user));
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        await logout();
      }
    }
  }, [logout]);

  const value = useMemo(
    () => ({ user, loading, login, logout, refreshUser }),
    [user, loading, login, logout, refreshUser],
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
