package com.churchmanager.benin

import android.content.ContentValues
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.provider.MediaStore
import android.util.Base64
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReactContextBaseJavaModule
import com.facebook.react.bridge.ReactMethod
import java.io.File
import java.io.FileOutputStream

/**
 * Écrit les fichiers partagés via MediaStore : aucun accès « Tous les fichiers »
 * n'est nécessaire. Les fichiers restent visibles dans Téléchargements et
 * survivent à la désinstallation.
 */
class FinanceMediaStoreModule(private val reactContext: ReactApplicationContext) :
    ReactContextBaseJavaModule(reactContext) {

    override fun getName(): String = "FinanceMediaStore"

    @ReactMethod
    fun saveDownload(base64: String, fileName: String, mimeType: String, promise: Promise) {
        try {
            val bytes = Base64.decode(base64, Base64.DEFAULT)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val values = ContentValues().apply {
                    put(MediaStore.Downloads.DISPLAY_NAME, fileName)
                    put(MediaStore.Downloads.MIME_TYPE, mimeType)
                    put(MediaStore.Downloads.IS_PENDING, 1)
                }
                val uri = reactContext.contentResolver.insert(
                    MediaStore.Downloads.EXTERNAL_CONTENT_URI,
                    values,
                ) ?: throw IllegalStateException("Insertion Téléchargements impossible")

                write(uri, bytes)

                values.clear()
                values.put(MediaStore.Downloads.IS_PENDING, 0)
                reactContext.contentResolver.update(uri, values, null, null)
                promise.resolve(uri.toString())
            } else {
                val dir = Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS)
                dir.mkdirs()
                val file = File(dir, fileName)
                FileOutputStream(file).use { it.write(bytes) }
                promise.resolve(Uri.fromFile(file).toString())
            }
        } catch (error: Exception) {
            promise.reject("SAVE_DOWNLOAD_FAILED", error.message, error)
        }
    }

    @ReactMethod
    fun saveDocument(
        base64: String,
        relativePath: String,
        fileName: String,
        mimeType: String,
        promise: Promise,
    ) {
        try {
            val bytes = Base64.decode(base64, Base64.DEFAULT)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val collection = MediaStore.Files.getContentUri("external")
                val values = ContentValues().apply {
                    put(MediaStore.MediaColumns.DISPLAY_NAME, fileName)
                    put(MediaStore.MediaColumns.MIME_TYPE, mimeType)
                    put(MediaStore.MediaColumns.RELATIVE_PATH, relativePath)
                    put(MediaStore.MediaColumns.IS_PENDING, 1)
                }
                val uri = reactContext.contentResolver.insert(collection, values)
                    ?: throw IllegalStateException("Insertion Documents impossible")

                write(uri, bytes)

                values.clear()
                values.put(MediaStore.MediaColumns.IS_PENDING, 0)
                reactContext.contentResolver.update(uri, values, null, null)
                promise.resolve(uri.toString())
            } else {
                val dir = File(Environment.getExternalStorageDirectory(), relativePath)
                dir.mkdirs()
                val file = File(dir, fileName)
                FileOutputStream(file).use { it.write(bytes) }
                promise.resolve(Uri.fromFile(file).toString())
            }
        } catch (error: Exception) {
            promise.reject("SAVE_DOCUMENT_FAILED", error.message, error)
        }
    }

    @ReactMethod
    fun readDocument(relativePath: String, fileName: String, promise: Promise) {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                val collection = MediaStore.Files.getContentUri("external")
                val selection =
                    "${MediaStore.MediaColumns.RELATIVE_PATH}=? AND ${MediaStore.MediaColumns.DISPLAY_NAME}=?"
                val cursor = reactContext.contentResolver.query(
                    collection,
                    arrayOf(MediaStore.MediaColumns._ID),
                    selection,
                    arrayOf(relativePath, fileName),
                    null,
                )

                cursor?.use {
                    if (it.moveToFirst()) {
                        val id = it.getLong(it.getColumnIndexOrThrow(MediaStore.MediaColumns._ID))
                        val uri = Uri.withAppendedId(collection, id)
                        val bytes = reactContext.contentResolver.openInputStream(uri)?.use { input ->
                            input.readBytes()
                        }
                        if (bytes != null) {
                            promise.resolve(Base64.encodeToString(bytes, Base64.NO_WRAP))
                            return
                        }
                    }
                }
                promise.resolve(null)
            } else {
                val file = File(File(Environment.getExternalStorageDirectory(), relativePath), fileName)
                if (!file.exists()) {
                    promise.resolve(null)
                } else {
                    val bytes = file.readBytes()
                    promise.resolve(Base64.encodeToString(bytes, Base64.NO_WRAP))
                }
            }
        } catch (error: Exception) {
            promise.reject("READ_DOCUMENT_FAILED", error.message, error)
        }
    }

    private fun write(uri: Uri, bytes: ByteArray) {
        val output = reactContext.contentResolver.openOutputStream(uri, "w")
            ?: throw IllegalStateException("Écriture impossible")
        output.use {
            it.write(bytes)
            it.flush()
        }
    }
}
