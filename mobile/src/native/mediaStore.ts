import { NativeModules, Platform } from 'react-native';

interface FinanceMediaStoreNative {
  saveDownload(base64: string, fileName: string, mimeType: string): Promise<string>;
  saveDocument(
    base64: string,
    relativePath: string,
    fileName: string,
    mimeType: string,
  ): Promise<string>;
  readDocument(relativePath: string, fileName: string): Promise<string | null>;
}

const native = NativeModules.FinanceMediaStore as FinanceMediaStoreNative | undefined;

function module(): FinanceMediaStoreNative {
  if (Platform.OS !== 'android' || !native) {
    throw new Error('Module de stockage Android indisponible.');
  }
  return native;
}

export function saveToDownloads(
  base64: string,
  fileName: string,
  mimeType: string,
): Promise<string> {
  return module().saveDownload(base64, fileName, mimeType);
}

export function saveToDocuments(
  base64: string,
  relativePath: string,
  fileName: string,
  mimeType: string,
): Promise<string> {
  return module().saveDocument(base64, relativePath, fileName, mimeType);
}

export function readFromDocuments(
  relativePath: string,
  fileName: string,
): Promise<string | null> {
  return module().readDocument(relativePath, fileName);
}
