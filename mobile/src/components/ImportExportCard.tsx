import * as DocumentPicker from 'expo-document-picker';
import React, { useState } from 'react';
import { ActivityIndicator, Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { apiDownload, apiUpload, ApiError, getErrorMessage, invalidateCache } from '../api/client';
import type { ImportResult } from '../api/types';
import { isDownloadedFile } from '../db/storage';
import { Card, colors, SectionTitle } from './ui';

const EXCEL_MIME =
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

export function ImportExportCard({
  title,
  help,
  importPath,
  templatePath,
  templateFileName,
  onImported,
}: {
  title: string;
  help: string;
  importPath: string;
  templatePath: string;
  templateFileName: string;
  onImported?: () => void | Promise<void>;
}) {
  const [uploading, setUploading] = useState(false);
  const [downloading, setDownloading] = useState(false);

  const importFile = async () => {
    try {
      const picked = await DocumentPicker.getDocumentAsync({
        type: [EXCEL_MIME, 'application/vnd.ms-excel'],
        copyToCacheDirectory: true,
      });

      if (picked.canceled || !picked.assets?.length) {
        return;
      }

      const asset = picked.assets[0];
      if (!/\.xlsx$/i.test(asset.name ?? '')) {
        Alert.alert(
          'Format invalide',
          'Choisissez un fichier Excel au format .xlsx.',
        );
        return;
      }

      setUploading(true);
      const result = await apiUpload<ImportResult>(importPath, {
        uri: asset.uri,
        name: asset.name,
        mimeType: asset.mimeType ?? EXCEL_MIME,
      });

      invalidateCache();
      await onImported?.();

      const details =
        result.errors && result.errors.length > 0
          ? `\n\n${result.errors.slice(0, 5).join('\n')}`
          : '';
      Alert.alert('Import terminé', `${result.message}${details}`);
    } catch (caught) {
      const errors = caught instanceof ApiError ? caught.errors : undefined;
      const details = errors
        ? Object.values(errors).flat().join('\n')
        : '';
      Alert.alert(
        'Import impossible',
        details || getErrorMessage(caught, "L'import a échoué."),
      );
    } finally {
      setUploading(false);
    }
  };

  const downloadTemplate = async () => {
    try {
      setDownloading(true);
      const uri = await apiDownload(templatePath, templateFileName);
      const location = isDownloadedFile(uri)
        ? `Enregistré dans Téléchargements/${templateFileName}\n(copie conservée dans Documents/FinanceMobile/files).`
        : `Le fichier est disponible ici : ${uri}`;

      Alert.alert('Modèle téléchargé', location);
    } catch (caught) {
      Alert.alert(
        'Téléchargement impossible',
        getErrorMessage(caught, "Le modèle n'a pas pu être téléchargé."),
      );
    } finally {
      setDownloading(false);
    }
  };

  return (
    <Card>
      <SectionTitle>{title}</SectionTitle>
      <Text style={styles.help}>{help}</Text>
      <View style={styles.actions}>
        <View style={styles.action}>
          <Pressable
            style={({ pressed }) => [styles.button, styles.primary, pressed && styles.pressed]}
            onPress={() => void importFile()}
            disabled={uploading}
          >
            {uploading ? (
              <ActivityIndicator color="#FFFFFF" />
            ) : (
              <Text style={styles.primaryText}>Importer depuis Excel</Text>
            )}
          </Pressable>
        </View>
        <View style={styles.action}>
          <Pressable
            style={({ pressed }) => [styles.button, styles.secondary, pressed && styles.pressed]}
            onPress={() => void downloadTemplate()}
            disabled={downloading}
          >
            {downloading ? (
              <ActivityIndicator color={colors.blue} />
            ) : (
              <Text style={styles.secondaryText}>Télécharger le modèle</Text>
            )}
          </Pressable>
        </View>
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  help: { fontSize: 13, color: colors.muted, lineHeight: 19, marginBottom: 14 },
  actions: { flexDirection: 'row', gap: 14 },
  action: { flex: 1 },
  button: {
    minHeight: 46,
    borderRadius: 12,
    paddingHorizontal: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: { backgroundColor: colors.blue },
  secondary: { backgroundColor: colors.lightBlue, borderWidth: 1, borderColor: colors.border },
  primaryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 13, textAlign: 'center' },
  secondaryText: { color: colors.blue, fontWeight: '700', fontSize: 13, textAlign: 'center' },
  pressed: { opacity: 0.75 },
});
