import React from 'react';
import { ApiError, getErrorMessage, isNetworkError } from '../api/client';
import { ErrorScreen } from './ErrorScreen';

interface Props {
  children: React.ReactNode;
}

interface State {
  error: Error | null;
}

/** Remplace l'application par une page d'erreur française en cas de crash de rendu. */
export class ErrorBoundary extends React.Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo): void {
    console.error('Erreur de rendu de FinanceMobile', error, info.componentStack);
  }

  private handleRetry = (): void => {
    this.setState({ error: null });
  };

  render(): React.ReactNode {
    const { error } = this.state;

    if (error) {
      return (
        <ErrorScreen
          title={isNetworkError(error) ? 'Serveur indisponible' : 'Une erreur est survenue'}
          message={
            error instanceof ApiError
              ? getErrorMessage(error, 'Une erreur est survenue. Veuillez réessayer.')
              : 'Une erreur inattendue est survenue. Veuillez réessayer.'
          }
          onRetry={this.handleRetry}
        />
      );
    }

    return this.props.children;
  }
}
