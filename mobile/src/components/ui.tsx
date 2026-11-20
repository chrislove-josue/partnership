import React, { useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Linking,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import appConfig from '../../app.json';

export const APP_VERSION = appConfig.expo.version;

export const colors = {
  navy: '#0F2B46',
  blue: '#1F4E78',
  lightBlue: '#EAF2FA',
  gold: '#D4AF37',
  green: '#15803D',
  red: '#B91C1C',
  ink: '#14213D',
  muted: '#64748B',
  border: '#D8E1EC',
  surface: '#FFFFFF',
  background: '#F4F7FB',
};

export function formatMoney(value: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value)} FCFA`;
}

export function formatDate(value?: string | null): string {
  if (!value) {
    return '—';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date);
}

export function Screen({
  title,
  subtitle,
  actions,
  children,
}: {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <SafeAreaView style={styles.safe} edges={['top', 'left', 'right']}>
      <View style={styles.header}>
        <View style={styles.headerText}>
          <Text style={styles.title}>{title}</Text>
          {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
        </View>
        {actions ? <View style={styles.headerActions}>{actions}</View> : null}
      </View>
      <ScrollView style={styles.scroll} contentContainerStyle={styles.scrollContent}>
        {children}
        <AppFooter />
      </ScrollView>
    </SafeAreaView>
  );
}

export function AppFooter({ compact = false }: { compact?: boolean }) {
  return (
    <View style={[styles.appFooter, compact && styles.appFooterCompact]}>
      <Pressable
        onPress={() => void Linking.openURL('https://wa.me/22998123353')}
        accessibilityRole="link"
        accessibilityLabel="Contacter l'assistance par WhatsApp"
      >
        <Text style={styles.appFooterLink} numberOfLines={1}>
          Pour toute assistance : +229 01 98 12 33 53
        </Text>
      </Pressable>
      <Text style={styles.appFooterText} numberOfLines={1}>
        Conçu et Développé par Nestor ATCHOUGO
      </Text>
      <Text style={styles.appFooterVersion} numberOfLines={1}>
        Version {APP_VERSION}
      </Text>
    </View>
  );
}

export function Card({ children, style }: { children: React.ReactNode; style?: object }) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function SectionTitle({ children }: { children: React.ReactNode }) {
  return <Text style={styles.sectionTitle}>{children}</Text>;
}

export function PrimaryButton({
  title,
  onPress,
  disabled,
  loading,
}: {
  title: string;
  onPress: () => void;
  disabled?: boolean;
  loading?: boolean;
}) {
  return (
    <Pressable
      style={({ pressed }) => [styles.button, styles.primary, pressed && styles.pressed, disabled && styles.disabled]}
      onPress={onPress}
      disabled={disabled || loading}
    >
      {loading ? (
        <ActivityIndicator color="#FFFFFF" />
      ) : (
        <Text style={styles.primaryText}>{title}</Text>
      )}
    </Pressable>
  );
}

export function SecondaryButton({
  title,
  onPress,
  disabled,
}: {
  title: string;
  onPress: () => void;
  disabled?: boolean;
}) {
  return (
    <Pressable
      style={({ pressed }) => [styles.button, styles.secondary, pressed && styles.pressed, disabled && styles.disabled]}
      onPress={onPress}
      disabled={disabled}
    >
      <Text style={styles.secondaryText}>{title}</Text>
    </Pressable>
  );
}

export function TextField({
  label,
  value,
  onChangeText,
  placeholder,
  secureTextEntry,
  keyboardType = 'default',
  multiline,
  trailing,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder?: string;
  secureTextEntry?: boolean;
  keyboardType?: 'default' | 'email-address' | 'phone-pad' | 'numeric' | 'decimal-pad';
  multiline?: boolean;
  trailing?: React.ReactNode;
}) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.inputContainer}>
        <TextInput
          style={[styles.input, trailing ? styles.inputWithTrailing : null, multiline && styles.multiline]}
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={colors.muted}
          secureTextEntry={secureTextEntry}
          keyboardType={keyboardType}
          multiline={multiline}
        />
        {trailing ? <View style={styles.trailing}>{trailing}</View> : null}
      </View>
    </View>
  );
}

export interface Option {
  label: string;
  value: string;
}

export function SelectField({
  label,
  value,
  options,
  onChange,
  placeholder = 'Sélectionner',
}: {
  label: string;
  value: string;
  options: Option[];
  onChange: (value: string) => void;
  placeholder?: string;
}) {
  const [open, setOpen] = useState(false);
  const selected = options.find((option) => option.value === value);

  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <Pressable style={styles.select} onPress={() => setOpen(true)}>
        <Text style={selected ? styles.selectValue : styles.selectPlaceholder}>
          {selected?.label ?? placeholder}
        </Text>
        <Text style={styles.chevron}>▾</Text>
      </Pressable>
      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <Pressable style={styles.modalBackdrop} onPress={() => setOpen(false)}>
          <View style={styles.selectModal}>
            <Text style={styles.modalTitle}>{label}</Text>
            <FlatList
              data={options}
              keyExtractor={(item) => item.value}
              renderItem={({ item }) => (
                <Pressable
                  style={({ pressed }) => [styles.option, pressed && styles.pressed, item.value === value && styles.optionSelected]}
                  onPress={() => {
                    onChange(item.value);
                    setOpen(false);
                  }}
                >
                  <Text style={item.value === value ? styles.optionTextSelected : styles.optionText}>{item.label}</Text>
                </Pressable>
              )}
            />
            <SecondaryButton title="Fermer" onPress={() => setOpen(false)} />
          </View>
        </Pressable>
      </Modal>
    </View>
  );
}

const MONTH_LABELS = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
];

/** Date saisie uniquement par sélection (jour / mois / année), sans clavier. */
export function DateField({
  label,
  value,
  onChange,
  allowEmpty = false,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  allowEmpty?: boolean;
}) {
  const currentYear = new Date().getFullYear();
  const parts = value ? value.split('-') : [];
  const year = parts[0] ?? '';
  const month = parts[1] ?? '';
  const day = parts[2] ?? '';

  const emptyOption: Option[] = allowEmpty ? [{ label: '—', value: '' }] : [];
  const yearOptions = [
    ...emptyOption,
    ...Array.from({ length: 100 }, (_, index) => ({
      label: String(currentYear - index),
      value: String(currentYear - index),
    })),
  ];
  const monthOptions = [
    ...emptyOption,
    ...MONTH_LABELS.map((name, index) => ({
      label: name,
      value: String(index + 1).padStart(2, '0'),
    })),
  ];
  const maxDay = year && month ? new Date(Number(year), Number(month), 0).getDate() : 31;
  const dayOptions = [
    ...emptyOption,
    ...Array.from({ length: maxDay }, (_, index) => String(index + 1).padStart(2, '0')),
  ];

  const emit = (nextYear: string, nextMonth: string, nextDay: string) => {
    if (!nextYear || !nextMonth || !nextDay) {
      onChange('');
      return;
    }

    const lastDay = new Date(Number(nextYear), Number(nextMonth), 0).getDate();
    const safeDay = String(Math.min(Number(nextDay), lastDay)).padStart(2, '0');
    onChange(`${nextYear}-${nextMonth}-${safeDay}`);
  };

  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.dateRow}>
        <View style={styles.datePart}>
          <SelectField label="Jour" value={day} options={dayOptions} onChange={(next) => emit(year, month, next)} placeholder="Jour" />
        </View>
        <View style={styles.datePart}>
          <SelectField label="Mois" value={month} options={monthOptions} onChange={(next) => emit(year, next, day)} placeholder="Mois" />
        </View>
        <View style={styles.datePart}>
          <SelectField label="Année" value={year} options={yearOptions} onChange={(next) => emit(next, month, day)} placeholder="Année" />
        </View>
      </View>
    </View>
  );
}

export function CheckboxField({
  label,
  checked,
  onChange,
}: {
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}) {
  return (
    <Pressable style={styles.checkboxRow} onPress={() => onChange(!checked)}>
      <View style={[styles.checkbox, checked && styles.checkboxChecked]}>
        {checked ? <Text style={styles.checkMark}>✓</Text> : null}
      </View>
      <Text style={styles.checkboxLabel}>{label}</Text>
    </Pressable>
  );
}

export function Badge({
  text,
  color = colors.blue,
}: {
  text: string;
  color?: string;
}) {
  return (
    <View style={[styles.badge, { backgroundColor: `${color}18`, borderColor: color }]}>
      <Text style={[styles.badgeText, { color }]}>{text}</Text>
    </View>
  );
}

export function EmptyState({ message }: { message: string }) {
  return (
    <Card style={styles.empty}>
      <Text style={styles.emptyText}>{message}</Text>
    </Card>
  );
}

export function LoadingView({ message = 'Chargement…' }: { message?: string }) {
  return (
    <View style={styles.loading}>
      <ActivityIndicator size="large" color={colors.blue} />
      <Text style={styles.loadingText}>{message}</Text>
    </View>
  );
}

export function ErrorView({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <Card style={styles.errorCard}>
      <Text style={styles.errorText}>{message}</Text>
      {onRetry ? <SecondaryButton title="Réessayer" onPress={onRetry} /> : null}
    </Card>
  );
}

export function ConfirmModal({
  visible,
  title,
  message,
  confirmLabel = 'Confirmer',
  onConfirm,
  onCancel,
  children,
}: {
  visible: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  onConfirm: () => void;
  onCancel: () => void;
  children?: React.ReactNode;
}) {
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onCancel}>
      <Pressable style={styles.modalBackdrop} onPress={onCancel}>
        <Pressable style={styles.confirmCard} onPress={() => undefined}>
          <Text style={styles.modalTitle}>{title}</Text>
          <Text style={styles.modalMessage}>{message}</Text>
          {children}
          <View style={styles.row}>
            <SecondaryButton title="Annuler" onPress={onCancel} />
            <PrimaryButton title={confirmLabel} onPress={onConfirm} />
          </View>
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  header: {
    paddingHorizontal: 20,
    paddingTop: 16,
    paddingBottom: 14,
    backgroundColor: colors.surface,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  headerText: { flex: 1 },
  headerActions: { flexDirection: 'row', gap: 8 },
  title: { fontSize: 22, fontWeight: '700', color: colors.navy },
  subtitle: { fontSize: 13, color: colors.muted, marginTop: 3 },
  scroll: { flex: 1 },
  scrollContent: { padding: 16, paddingBottom: 32, flexGrow: 1 },
  card: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: colors.border,
  },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.navy, marginBottom: 10 },
  button: {
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 18,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 46,
  },
  primary: { backgroundColor: colors.blue },
  primaryText: { color: '#FFFFFF', fontWeight: '700', fontSize: 15 },
  secondary: { backgroundColor: colors.lightBlue, borderWidth: 1, borderColor: colors.border },
  secondaryText: { color: colors.blue, fontWeight: '700', fontSize: 15 },
  disabled: { opacity: 0.5 },
  pressed: { opacity: 0.75 },
  field: { marginBottom: 14 },
  dateRow: { flexDirection: 'row', gap: 8 },
  datePart: { flex: 1 },
  label: { fontSize: 13, fontWeight: '600', color: colors.ink, marginBottom: 6 },
  inputContainer: { position: 'relative' },
  input: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    color: colors.ink,
    fontSize: 15,
  },
  inputWithTrailing: { paddingRight: 46 },
  trailing: { position: 'absolute', right: 12, top: 0, bottom: 0, justifyContent: 'center' },
  multiline: { minHeight: 90, textAlignVertical: 'top' },
  select: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 13,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  selectValue: { color: colors.ink, fontSize: 15 },
  selectPlaceholder: { color: colors.muted, fontSize: 15 },
  chevron: { color: colors.blue, fontSize: 16 },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(15,43,70,0.45)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  selectModal: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 16,
    width: '100%',
    maxHeight: '75%',
  },
  confirmCard: {
    backgroundColor: colors.surface,
    borderRadius: 16,
    padding: 20,
    width: '100%',
  },
  modalTitle: { fontSize: 18, fontWeight: '700', color: colors.navy, marginBottom: 8 },
  modalMessage: { fontSize: 14, color: colors.muted, lineHeight: 20, marginBottom: 14 },
  option: { paddingVertical: 12, paddingHorizontal: 10, borderRadius: 10 },
  optionSelected: { backgroundColor: colors.lightBlue },
  optionText: { color: colors.ink, fontSize: 15 },
  optionTextSelected: { color: colors.blue, fontWeight: '700', fontSize: 15 },
  checkboxRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, gap: 10 },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: colors.blue,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxChecked: { backgroundColor: colors.blue },
  checkMark: { color: '#FFFFFF', fontWeight: '700', fontSize: 14 },
  checkboxLabel: { color: colors.ink, fontSize: 14, flex: 1 },
  badge: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
    alignSelf: 'flex-start',
  },
  badgeText: { fontSize: 12, fontWeight: '700' },
  empty: { alignItems: 'center', paddingVertical: 28 },
  emptyText: { color: colors.muted, textAlign: 'center' },
  loading: { flex: 1, justifyContent: 'center', alignItems: 'center', gap: 12 },
  loadingText: { color: colors.muted },
  errorCard: { borderColor: colors.red },
  errorText: { color: colors.red, marginBottom: 12 },
  row: { flexDirection: 'row', gap: 16, justifyContent: 'flex-end', marginTop: 16 },
  appFooter: {
    width: '100%',
    marginTop: 'auto',
    paddingTop: 24,
    alignItems: 'center',
    gap: 8,
  },
  appFooterCompact: { paddingTop: 18 },
  appFooterLink: { fontSize: 13, color: colors.blue, textAlign: 'center', textDecorationLine: 'underline' },
  appFooterText: { fontSize: 13, fontWeight: '600', color: colors.ink, textAlign: 'center' },
  appFooterVersion: { fontSize: 12, color: colors.muted, textAlign: 'center' },
});
